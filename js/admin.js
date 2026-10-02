/**
 * NL Design System Theme - Admin Settings JavaScript
 */

;(function nldesignAdminInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', nldesignAdminMain)
	} else {
		nldesignAdminMain()
	}
	function nldesignAdminMain() {
		// Pure token / colour transforms, extracted to js/lib/tokenTransforms.js so
		// they can be unit-tested offline (tests/vitest/). Registered on
		// window.NldesignTokenTransforms by that script (loaded before this one). The
		// `|| {}` fallback keeps admin.js working even if the helper script is absent.
		var TT =
			(typeof window !== 'undefined' && window.NldesignTokenTransforms) || {}

		/**
		 * Show a transient toast, never breaking the flow that raised it.
		 *
		 * `OC.Notification` was REMOVED in Nextcloud 34 (toasts live in
		 * `OCP.Toast` / @nextcloud/dialogs now), so the old
		 * `notify()` call throws a TypeError. Because those
		 * calls sit inside `.then()` handlers, the throw aborted the rest of the
		 * handler — e.g. an upload succeeded server-side but `loadCustomTokenSets()`
		 * never ran, so the list silently never refreshed.
		 *
		 * A purely cosmetic notification must never be able to break a functional
		 * flow, so every failure path here is swallowed and downgraded to a log.
		 */
		function notify(message) {
			try {
				if (
					window.OCP
					&& OCP.Toast
					&& typeof OCP.Toast.message === 'function'
				) {
					OCP.Toast.message(message)
					return
				}
				if (
					window.OC
					&& OC.Notification
					&& typeof OC.Notification.showTemporary === 'function'
				) {
					OC.Notification.showTemporary(message)
					return
				}
			} catch (e) {
				// Fall through to the console fallback below.
			}
			console.info('[nldesign] ' + message)
		}

		/**
		 * Read a server-provided value out of Nextcloud's initial state.
		 *
		 * This is the canonical direction of travel for server data (ADR-004):
		 * `IInitialState::provideInitialState()` in PHP, `loadState()` here. It
		 * replaces four `getAttribute('data-…')` reads that pulled the same
		 * values off server-rendered DOM nodes. Data attributes are not merely
		 * unidiomatic — a CSP-hardened instance and any markup change can move or
		 * drop the carrying element, and the failure is silent: the parse yields
		 * the fallback and the panel renders as if the server had sent nothing.
		 *
		 * `loadState` throws when a key is absent and no fallback is supplied, so
		 * every call here passes one and the throw is contained. A missing key is
		 * a server-side bug, not a reason to break the whole settings panel.
		 *
		 * @param {string} key      The initial-state key, as provided by lib/Settings/Admin.php.
		 * @param {*}      fallback Value to use when the key is absent or unreadable.
		 * @return {*} The decoded value, or `fallback`.
		 */
		function loadInitialState(key, fallback) {
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
			} catch (e) {
				console.error(
					'[nldesign] initial state "' + key + '" could not be read:',
					e,
				)
				return fallback
			}
		}

		var settingsEl = document.getElementById('nldesign-settings')
		// The planned switch that is running, as `{ tokenSet, until }`, or null.
		// It puts its token set back on every job run, so a set picked in the
		// dropdown meanwhile would not last; the page says so beside the dropdown
		// and in the apply dialog.
		var runningSwitch = null
		var tokenSetSelect = document.getElementById('nldesign-token-set-select')
		var hideSloganCheckbox = document.getElementById('nldesign-hide-slogan')
		var primaryDrivesComponentsCheckbox = document.getElementById(
			'nldesign-primary-drives-components',
		)
		var previewRoot = document.getElementById('nldesign-preview')

		// Whether the brand primary currently overrules the component tokens it
		// used to drive. Read off the server-rendered checkbox rather than through
		// initial state: the template already carries the value, and the flag has
		// to stay in step with the control the admin is looking at.
		var primaryDrivesComponents =
			primaryDrivesComponentsCheckbox !== null
			&& primaryDrivesComponentsCheckbox.checked === true

		/**
		 * Whether a token's control is locked by the "primary drives every
		 * component" setting.
		 *
		 * Only component tokens carry `primary`. The brand tokens never lock —
		 * that setting exists to make THEM win, so freezing them would leave the
		 * admin with no way to change anything at all.
		 *
		 * @param {string} name The CSS custom property name.
		 *
		 * @return {boolean} True when the row must render disabled.
		 */
		function isTokenLocked(name) {
			var meta = tokenRegistry[name]
			if (meta === undefined) {
				return false
			}
			if (meta.group === 'brand' && baseTokensUnlocked === false) {
				return true
			}
			return primaryDrivesComponents === true && meta.primary === true
		}

		/**
		 * Whether Nextcloud's own base tokens can be edited.
		 *
		 * Off until the admin asks, and only for this visit. A base token —
		 * `--color-main-text`, `--color-primary-element` — is read by far more of
		 * Nextcloud than any one component, so changing one repaints things an
		 * admin did not set out to change. The component rows are the everyday
		 * control; these stay behind a deliberate, warned opt-in.
		 *
		 * @type {boolean}
		 */
		var baseTokensUnlocked = false

		/**
		 * Why a locked row is locked, for its tooltip.
		 *
		 * @param {string} name The CSS custom property name.
		 *
		 * @return {string} The reason, or '' when the row is not locked.
		 */
		function lockReason(name) {
			if (isTokenLocked(name) === false) {
				return ''
			}
			var meta = tokenRegistry[name]
			if (meta !== undefined && meta.group === 'brand') {
				return t(
					'thematiq',
					'A Nextcloud base color, read far beyond any one component. Tick "Also edit Nextcloud\'s base tokens" to change it.',
				)
			}
			return t(
				'thematiq',
				'The primary color drives this component. Switch off "Let the primary color drive every component" to set it separately.',
			)
		}

		// Read a live CSS custom property with a fallback. Reads from <body> first
		// (Nextcloud and the nldesign themes set their token vars there), then :root.
		function readVar(name, fallback) {
			var v = (
				getComputedStyle(document.body).getPropertyValue(name) || ''
			).trim()
			if (v === '') {
				v = (
					getComputedStyle(document.documentElement).getPropertyValue(name)
					|| ''
				).trim()
			}
			return v || fallback
		}

		// Token set inventory, provided by lib/Settings/Admin.php. Already decoded
		// by loadState — there is no JSON.parse here on purpose, because the
		// transport is no longer a string attribute.
		var tokenSetsData = {}
		var tokenSets = loadInitialState('tokenSets', [])
		if (Array.isArray(tokenSets) === true) {
			tokenSets.forEach(function (ts) {
				tokenSetsData[ts.id] = ts
			})
		}

		// The requesting admin's active theme preview ("proefdraaien"), if any —
		// provided by lib/Settings/Admin.php through the same channel.
		//
		// Normalised to null when there is no preview. "No preview" can arrive
		// three ways — the key absent (fallback), an empty array (what the PHP
		// side sends, because Nextcloud's initial state refuses a bare null), or
		// a payload without a token set — and `[]` is TRUTHY in JavaScript, so a
		// bare `!== null` test further down would have treated an empty array as
		// a live preview and then read `.tokenSet` off it as undefined.
		var activePreview = loadInitialState('activePreview', null)
		if (activePreview === null || typeof activePreview.tokenSet !== 'string') {
			activePreview = null
		}

		// Which source decided the active icon pack: the appconfig `icon_pack`
		// override, or the token set. When an override is active it always wins,
		// so the client-side indicator must not be re-derived from the dropdown.
		var iconPackSource = loadInitialState('iconPackSource', '')

		/* ==========================================================================
		 * APPLY WITHOUT A RELOAD
		 *
		 * A token set reaches the page as a run of <link>/<style> elements the
		 * server emits (CssInjectionService). Applying a set here means asking
		 * the server which elements the current set and the new set produce
		 * (GET /settings/tokenset-stylesheets/{id}) and swapping one run for
		 * the other — js/lib/layerSwap.js does the DOM work. Nothing in this
		 * block decides what a set consists of; the manifest does.
		 * ========================================================================== */

		var LayerSwap =
			typeof window !== 'undefined' && window.NldesignLayerSwap
				? window.NldesignLayerSwap
				: null

		// The set whose stylesheet run is on THIS page right now. Starts as
		// what the server rendered: an active preview wins, exactly as the
		// render did.
		var pageTokenSetId =
			activePreview !== null
				? activePreview.tokenSet
				: loadInitialState('currentTokenSet', '')
		// Its manifest, so the first swap knows which elements to remove.
		var pageManifest = null

		function fetchLayerManifest(tokenSetId) {
			return fetch(
				OC.generateUrl(
					'/apps/thematiq/settings/tokenset-stylesheets/'
						+ encodeURIComponent(tokenSetId),
				),
				{ headers: { requesttoken: OC.requestToken } },
			).then(function (response) {
				if (response.ok !== true) {
					throw new Error('Stylesheet manifest: HTTP ' + response.status)
				}
				return response.json()
			})
		}

		if (LayerSwap !== null && pageTokenSetId !== '') {
			fetchLayerManifest(pageTokenSetId)
				.then(function (manifest) {
					pageManifest = manifest
				})
				.catch(function () {
					// Unknown current run: the first swap inserts without removing,
					// and the newer run still wins the cascade.
					pageManifest = null
				})
		}

		/**
		 * Put a token set's stylesheets on this page, replacing the current
		 * set's. Resolves to true when the swap ran, false when the module is
		 * absent (the caller then falls back to asking for a reload).
		 */
		function applyLayersFor(tokenSetId) {
			if (LayerSwap === null) {
				return Promise.resolve(false)
			}

			var current =
				pageManifest !== null
					? Promise.resolve(pageManifest)
					: pageTokenSetId === ''
						? Promise.resolve(null)
						: fetchLayerManifest(pageTokenSetId).catch(function () {
								return null
							})

			return Promise.all([current, fetchLayerManifest(tokenSetId)]).then(
				function (manifests) {
					return LayerSwap.swap(document, manifests[0], manifests[1]).then(
						function (result) {
							// A sheet that 404'd or timed out leaves the page on
							// the OLD set (swap rolls back), so the page is still
							// the set it was: do not claim the new one, and let
							// the caller fall back to asking for a reload.
							if (result.ok !== true) {
								return false
							}

							pageTokenSetId = tokenSetId
							pageManifest = manifests[1]
							syncOverridesLink(tokenSetId)
							// For what follows the page's theme, such as the
							// brand form's starting colors.
							document.dispatchEvent(
								new CustomEvent('thematiq:theme-applied', {
									detail: { tokenSet: tokenSetId },
								}),
							)
							return true
						},
					)
				},
			)
		}

		/**
		 * The set whose overrides the editor reads and writes.
		 *
		 * The stock set keeps its edits in a file of its own and never loads
		 * the shared one (CustomOverridesService), so every overrides request
		 * names the set it means rather than leaving the server to assume the
		 * instance's active one. Read like onStockSet(): the dropdown first,
		 * because it can move under the editor without a reload.
		 *
		 * @return {string} The token set id, or '' when unknown.
		 */
		function editedTokenSetId() {
			if (tokenSetSelect !== null && tokenSetSelect.value !== '') {
				return tokenSetSelect.value
			}

			return pageTokenSetId
		}

		/**
		 * An overrides endpoint URL for one token set.
		 *
		 * @param {string} path     The path after /settings/overrides ('' or '/export').
		 * @param {string} tokenSet The token set id.
		 *
		 * @return {string} The URL.
		 */
		function overridesUrl(path, tokenSet) {
			return (
				OC.generateUrl('/apps/thematiq/settings/overrides' + path)
				+ '?tokenSet='
				+ encodeURIComponent(tokenSet)
			)
		}

		// Both overrides files: the shared one and the stock set's own.
		var OVERRIDES_LINK_SELECTOR =
			'link[rel="stylesheet"][href*="/thematiq/css/custom-overrides"]'

		/**
		 * custom-overrides.css changed under the same URL (the apply dialog or
		 * the token editor wrote it): make the browser fetch it again.
		 */
		function refreshCustomOverridesLink() {
			if (LayerSwap === null) {
				return
			}
			LayerSwap.refreshStylesheets(document, OVERRIDES_LINK_SELECTOR)
		}

		/**
		 * Point the overrides link at the file the given set reads.
		 *
		 * The server emits that link after the set's own run, so a swap without
		 * a reload leaves it on the OLD set's file: a switch to the stock set
		 * would keep painting the edits of the theme just left.
		 *
		 * @param {string} tokenSetId The set now on the page.
		 *
		 * @return {void}
		 */
		function syncOverridesLink(tokenSetId) {
			var link = document.querySelector(OVERRIDES_LINK_SELECTOR)
			if (link === null) {
				return
			}

			// The same rule as CustomOverridesService::fileFor(): a set on no
			// design system has a file of its own, every other set the shared one.
			var option =
				tokenSetSelect !== null
					? tokenSetSelect.querySelector(
							'option[value="' + tokenSetId + '"]',
						)
					: null
			var designSystem =
				option !== null
					? option.getAttribute('data-design-system') || 'nldesign'
					: 'nldesign'
			var file =
				designSystem === 'none' || tokenSetId === STOCK_TOKEN_SET
					? 'custom-overrides-'
						+ tokenSetId.toLowerCase().replace(/[^a-z0-9-]/g, '')
					: 'custom-overrides'
			var href = OC.filePath('thematiq', 'css', file + '.css')
			if (
				LayerSwap.pathnameOf(link.getAttribute('href'))
				=== LayerSwap.pathnameOf(href)
			) {
				return
			}

			link.setAttribute('href', href + '?v=' + Date.now())
		}

		/**
		 * The hide-slogan / show-menu-labels toggles each own one stylesheet
		 * that the server emits after the set layers. Add or remove it here so
		 * the toggle is true on this page the moment it is saved.
		 */
		function setConditionalLayer(file, enabled) {
			if (LayerSwap === null) {
				return
			}
			var path = OC.filePath('thematiq', 'css', file + '.css')
			var existing = null
			var links = document.querySelectorAll('link[rel="stylesheet"][href]')
			for (var index = 0; index < links.length; index++) {
				if (
					LayerSwap.pathnameOf(links[index].getAttribute('href'))
					=== LayerSwap.pathnameOf(path)
				) {
					existing = links[index]
				}
			}
			if (enabled === true && existing === null) {
				document.head.appendChild(
					LayerSwap.createLayerElement(document, {
						kind: 'file',
						layer: 'conditional',
						href: LayerSwap.bumpVersion(path, String(Date.now())),
					}),
				)
			} else if (enabled !== true && existing !== null) {
				existing.parentNode.removeChild(existing)
			}
		}

		/**
		 * After POST /settings/theming: core theming's generated stylesheets
		 * carry the new primary, background and logo — re-request them (what
		 * core's own admin panel does after a save) and write the new values
		 * into that panel's fields, which sit further up this same page.
		 * Resolves to the fresh GET /settings/theming snapshot.
		 */
		function refreshCoreTheming() {
			return fetch(OC.generateUrl('/apps/thematiq/settings/theming'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (response) {
					return response.json()
				})
				.then(function (snapshot) {
					if (LayerSwap !== null) {
						LayerSwap.refreshThemeStylesheets(document)
					}
					updateCoreThemingPanel(snapshot)
					return snapshot
				})
		}

		/**
		 * Write synced values into core's Theming panel on this page.
		 *
		 * The panel is core's Vue app; these are the `data-admin-theming-*`
		 * hooks it renders (apps/theming/src/AdminTheming.vue,
		 * ColorPickerField.vue, FileInputField.vue). Nothing here is fatal: a
		 * hook that is not on the page is skipped, and the panel keeps its own
		 * state until the admin interacts with it.
		 */
		function updateCoreThemingPanel(snapshot) {
			if (!snapshot) {
				return
			}
			// After a reset the snapshot values are empty, because the app
			// values were deleted; the field then shows what core falls back to.
			setCoreColorField(
				'[data-admin-theming-setting-primary-color]',
				snapshot.primary_color || snapshot.default_primary_color,
			)
			setCoreColorField(
				'[data-admin-theming-setting-background-color]',
				snapshot.background_color || snapshot.default_background_color,
			)

			var logoPreview = document.querySelector(
				'[data-admin-theming-preview-logo]',
			)
			if (logoPreview !== null && snapshot.logo_url) {
				logoPreview.style.backgroundImage = 'url(' + snapshot.logo_url + ')'
			}
		}

		/**
		 * Black or white, whichever core would put on this colour.
		 *
		 * Mirrors `colord(value).isLight()`, which ColorPickerField uses for
		 * its `calculatedTextColor`: perceived brightness over 0.5 gets black
		 * text. Reproduced rather than imported because colord is core's
		 * dependency, not ours, and this is four lines.
		 */
		function contrastTextFor(hex) {
			var rgb = /^#?([\da-f]{2})([\da-f]{2})([\da-f]{2})$/i.exec(
				String(hex).trim(),
			)
			if (rgb === null) {
				return '#ffffff'
			}
			var r = parseInt(rgb[1], 16)
			var g = parseInt(rgb[2], 16)
			var b = parseInt(rgb[3], 16)
			var brightness = Math.round((r * 299 + g * 587 + b * 114) / 1000) / 255

			return brightness > 0.5 ? '#000000' : '#ffffff'
		}

		/**
		 * Write a colour into one of core's Theming colour pickers.
		 *
		 * The picker BUTTON is not styled from an attribute or a class: core's
		 * `ColorPickerField.vue` uses `v-bind('value')` in its scoped style,
		 * which the build compiles to a hash-named custom property
		 * (`background-color: var(--6cc639bc)`) that Vue writes inline on the
		 * field's root element. So updating the label text left the button
		 * blue — the colour lives in that property, and nothing else does.
		 *
		 * The hash is derived from the file at build time and changes with any
		 * Nextcloud release, so it is DISCOVERED here instead of hard-coded:
		 * among the inline custom properties on the field root, the one that
		 * equals the value the button is currently displaying is the colour,
		 * and the one holding pure black or white is its text colour.
		 */
		function setCoreColorField(wrapperSelector, hex) {
			if (!hex) {
				return
			}
			var wrapper = document.querySelector(wrapperSelector)
			if (wrapper === null) {
				return
			}

			var button = wrapper.querySelector(
				'[data-admin-theming-setting-color-picker]',
			)
			var shown = ''
			if (button !== null) {
				shown = (button.textContent || '').trim().toLowerCase()
			}

			// Rebind the compiled v-bind custom properties. The field root
			// carries exactly two: the colour and its text colour, in that
			// order (the order the v-binds appear in core's style block).
			//
			// They are told apart by VALUE where possible — the colour is the
			// one the button is displaying — but a first pass that only did
			// that could not recover from white-on-white: when both properties
			// hold the same value, both matched the colour and the text stayed
			// invisible. So the match is claimed once, and whatever is left is
			// the text colour.
			var properties = []
			for (var index = 0; index < wrapper.style.length; index++) {
				if (wrapper.style[index].indexOf('--') === 0) {
					properties.push(wrapper.style[index])
				}
			}

			var valueProperty = null
			var textProperty = null
			properties.forEach(function (property) {
				var current = wrapper.style
					.getPropertyValue(property)
					.trim()
					.toLowerCase()
				if (valueProperty === null && shown !== '' && current === shown) {
					valueProperty = property
				} else if (
					textProperty === null
					&& (current === '#ffffff' || current === '#000000')
				) {
					textProperty = property
				}
			})
			// The field carries exactly these two, in this order (the order the
			// v-binds appear in core's style block), so anything the value/text
			// heuristics could not place is settled positionally. Without this
			// the text colour was only ever corrected when it already held pure
			// black or white — so a field left in some other state by an
			// earlier run stayed there, invisible, until a reload.
			if (properties.length === 2) {
				if (valueProperty === null) {
					valueProperty = properties[0]
				}
				if (textProperty === null || textProperty === valueProperty) {
					textProperty =
						properties[valueProperty === properties[0] ? 1 : 0]
				}
			}

			if (valueProperty !== null) {
				wrapper.style.setProperty(valueProperty, hex)
			}
			if (textProperty !== null) {
				wrapper.style.setProperty(textProperty, contrastTextFor(hex))
			}

			// The label reads the hex out loud; the separate preview square is
			// styled from the same property but is set directly too, so the
			// field is still right if a future core release drops the v-bind.
			if (button !== null) {
				var label = button.querySelector('.button-vue__text')
				if (label !== null) {
					label.textContent = hex
				} else {
					for (var node = 0; node < button.childNodes.length; node++) {
						var child = button.childNodes[node]
						if (
							child.nodeType === 3
							&& child.textContent.trim() !== ''
						) {
							child.textContent = hex
						}
					}
				}
			}
			var swatch = wrapper.querySelector('[data-admin-theming-setting-color]')
			if (swatch !== null) {
				swatch.style.backgroundColor = hex
			}
		}

		/**
		 * Re-read the selectable catalogue and bring the dropdown and
		 * tokenSetsData in line with it: an uploaded set gains its <option>,
		 * without a reload. Existing options keep their identity so a
		 * selection survives. Resolves to the catalogue list.
		 */
		function refreshTokenSetCatalogue() {
			return fetch(OC.generateUrl('/apps/thematiq/settings/tokensets'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (response) {
					return response.json()
				})
				.then(function (data) {
					var sets = (data && data.tokenSets) || []
					sets.forEach(function (ts) {
						tokenSetsData[ts.id] = ts
						upsertTokenSetOption(ts)
					})
					return sets
				})
		}

		function upsertTokenSetOption(ts) {
			if (tokenSetSelect === null || !ts || !ts.id) {
				return
			}
			var option = tokenSetSelect.querySelector(
				'option[value="' + ts.id + '"]',
			)
			if (option === null) {
				option = document.createElement('option')
				option.value = ts.id
				// The list is alphabetical by name (token-set-dropdown spec):
				// insert before the first option that sorts after this one.
				var before = null
				var options = tokenSetSelect.options
				for (var index = 0; index < options.length; index++) {
					var other = tokenSetsData[options[index].value]
					var otherName =
						other && other.name ? other.name : options[index].text
					if (
						otherName.localeCompare(ts.name || ts.id, undefined, {
							sensitivity: 'base',
						}) > 0
					) {
						before = options[index]
						break
					}
				}
				tokenSetSelect.insertBefore(option, before)
			}
			option.textContent = ts.name || ts.id
			option.setAttribute('data-design-system', ts.design_system || 'nldesign')
		}

		function removeTokenSetOption(id) {
			delete tokenSetsData[id]
			if (tokenSetSelect === null) {
				return
			}
			var option = tokenSetSelect.querySelector('option[value="' + id + '"]')
			if (option !== null) {
				option.remove()
			}
		}

		/**
		 * Make the dropdown show a set without opening the apply dialog (the
		 * change handler does that), and refresh everything that reads the
		 * selection.
		 */
		function reflectSelection(tokenSetId) {
			if (tokenSetSelect === null) {
				return
			}
			tokenSetSelect.value = tokenSetId
			tokenSetSelect.dataset.previousValue = tokenSetId
			updatePreview(tokenSetId)
			updateDesignSystemBadge(tokenSetId)
			updateCompletenessBadge(tokenSetId)
			updateIconPackIndicator(tokenSetId)
			updateMarianneVisibility(tokenSetId)
		}

		/**
		 * Derive preview colors dynamically from the token set's theming metadata
		 * (primary_color field in token-sets.json, already passed in the `tokenSets`
		 * initial state).
		 * Falls back to a neutral dark if no data is available.
		 * This replaces the old hardcoded 9-entry palette (issue #123).
		 */
		function getPreviewColors(tokenSetId) {
			var ts = tokenSetsData[tokenSetId]
			if (typeof TT.getPreviewColors === 'function') {
				return TT.getPreviewColors(ts)
			}
			// Inline fallback (mirrors js/lib/tokenTransforms.js getPreviewColors).
			var primary =
				ts && ts.theming && ts.theming.primary_color
					? ts.theming.primary_color
					: '#333333'
			return {
				primary: primary,
				primaryHover: darkenHex(primary, 0.1),
				primaryText: '#ffffff',
			}
		}

		/**
		 * Darken a hex colour by the given fraction (0–1).
		 * Delegates to the extracted pure helper, with an inline fallback.
		 */
		function darkenHex(hex, fraction) {
			if (typeof TT.darkenHex === 'function') {
				return TT.darkenHex(hex, fraction)
			}
			var m = /^#([0-9a-fA-F]{6})$/.exec(hex)
			if (m === null) {
				return hex
			}
			var r = Math.max(
				0,
				Math.round(parseInt(m[1].substring(0, 2), 16) * (1 - fraction)),
			)
			var g = Math.max(
				0,
				Math.round(parseInt(m[1].substring(2, 4), 16) * (1 - fraction)),
			)
			var b = Math.max(
				0,
				Math.round(parseInt(m[1].substring(4, 6), 16) * (1 - fraction)),
			)
			return (
				'#'
				+ ('0' + r.toString(16)).slice(-2)
				+ ('0' + g.toString(16)).slice(-2)
				+ ('0' + b.toString(16)).slice(-2)
			)
		}

		// Drive the rich preview (app shell + login) from the selected token set and
		// the live NC token values. Primary comes from the selected set's metadata
		// (it isn't applied until confirmed); the remaining colours mirror the live
		// theme, so the token-editor pickers reflect into the preview too.
		/**
		 * Repaint the rich preview from the live CSS stack.
		 *
		 * READ EVERYTHING, THEN WRITE EVERYTHING — and that split is the whole
		 * performance of this panel.
		 *
		 * This used to interleave the two: read a variable, set a `--prev-*`,
		 * read the next. Writing to `previewRoot.style` invalidates style for
		 * the subtree, so the read after it could not be served from the last
		 * computation and forced a fresh synchronous one. Eighteen properties
		 * meant eighteen full style recalculations, against a cascade that
		 * carries theme.css, element-overrides.css, component-scopes.css and
		 * the vendored guest sheet. The colour picker fires `input` on every
		 * pixel of a drag, so that ran dozens of times a second — which is what
		 * made the picker stutter.
		 *
		 * Reading first means one recalculation for the batch, and the writes
		 * afterwards cost nothing until the next frame paints.
		 *
		 * @param {string} tokenSet The token set id the preview is drawn for.
		 *
		 * @return {void}
		 */
		function updatePreview(tokenSet) {
			if (!previewRoot) {
				return
			}

			var colors = getPreviewColors(tokenSet)

			// ---- read phase -------------------------------------------------
			// Both declarations are resolved once here instead of per lookup;
			// `readVar` builds a new pair on every call.
			// Read on the preview itself: an unsaved edit is declared there,
			// and everything else reaches it by inheritance.
			var previewStyle = getComputedStyle(previewRoot)

			function read(name, fallback) {
				return (previewStyle.getPropertyValue(name) || '').trim() || fallback
			}

			var primaryText =
				colors.primaryText || read('--color-primary-text', '#ffffff')

			var values = {
				'--prev-primary': colors.primary,
				'--prev-primary-text': primaryText,
				'--prev-surface': read('--color-main-background', '#ffffff'),
				'--prev-bg': read('--color-background-dark', '#f2f4f7'),
				'--prev-text': read('--color-main-text', '#1b2733'),
				'--prev-muted': read('--color-text-maxcontrast', '#6b7785'),
				'--prev-border': read('--color-border', '#e3e9f0'),
				'--prev-warning': read('--color-warning', '#c79a00'),
				'--prev-error': read('--color-error', '#c0392b'),
				'--prev-info': read(
					'--color-info',
					read('--color-primary-element', colors.primary),
				),
				'--prev-radius': read('--border-radius-element', '8px'),
				// The rounded container Nextcloud clips the navigation and the
				// app content into, and the pill radius of a navigation entry.
				'--prev-radius-container': read(
					'--body-container-radius',
					read('--border-radius-large', '12px'),
				),
				'--prev-radius-pill': read('--border-radius-pill', '999px'),
				// The page background BEHIND the content container — visible in
				// the gap around it, which is where a themed instance shows its
				// plain colour or background image.
				'--prev-plain': read(
					'--color-background-plain',
					read('--color-background-dark', '#f2f4f7'),
				),
				// The header is its own role: a set may paint it differently
				// from the primary (Cunningham and Amsterdam both paint it white
				// over a blue primary). Fall back to the primary only when the
				// page carries no header token.
				'--prev-header-bg': read(
					'--nldesign-color-header-background',
					colors.primary,
				),
				'--prev-header-text': read(
					'--nldesign-color-header-text',
					primaryText,
				),
				'--prev-header-border': read('--nldesign-header-border-bottom', '0'),
				'--prev-login-bg': colors.primary,
			}

			// ---- write phase ------------------------------------------------
			var s = previewRoot.style
			Object.keys(values).forEach(function (name) {
				s.setProperty(name, values[name])
			})
		}

		// App / Login preview switch: a WAI-ARIA tablist. A click or the arrow,
		// Home and End keys select a view; only the selected tab is in the tab
		// order, the same way the "Add a custom token set" tabs work.
		if (previewRoot) {
			var previewTabs = Array.prototype.slice.call(
				previewRoot.querySelectorAll('.nldesign-preview-switch-btn'),
			)
			var selectPreviewTab = function (btn) {
				var view = btn.getAttribute('data-view')
				previewTabs.forEach(function (b) {
					var on = b === btn
					b.classList.toggle('active', on)
					b.setAttribute('aria-selected', on ? 'true' : 'false')
					b.tabIndex = on ? 0 : -1
				})
				previewRoot
					.querySelectorAll('.nldesign-preview-stage')
					.forEach(function (stage) {
						stage.hidden = stage.getAttribute('data-view') !== view
					})
			}
			previewTabs.forEach(function (btn, index) {
				btn.setAttribute('role', 'tab')
				btn.tabIndex = btn.classList.contains('active') ? 0 : -1
				btn.addEventListener('click', function () {
					selectPreviewTab(btn)
				})
				btn.addEventListener('keydown', function (event) {
					var next = null
					if (event.key === 'ArrowRight') {
						next = previewTabs[(index + 1) % previewTabs.length]
					} else if (event.key === 'ArrowLeft') {
						next =
							previewTabs[
								(index - 1 + previewTabs.length) % previewTabs.length
							]
					} else if (event.key === 'Home') {
						next = previewTabs[0]
					} else if (event.key === 'End') {
						next = previewTabs[previewTabs.length - 1]
					}
					if (next !== null) {
						event.preventDefault()
						selectPreviewTab(next)
						next.focus()
					}
				})
			})
		}

		// Design system display names (inline fallback for designSystemLabel()).
		var designSystemNames = {
			none: 'Stock Nextcloud',
			nldesign: 'NL Design System',
		}

		// Documentation link per design system id, from lib/Settings/Admin.php.
		var designSystemDocs = loadInitialState('designSystemDocs', {})

		/**
		 * Point the header's Documentation link at the docs of a design system.
		 *
		 * The template renders the current set's link; this follows the
		 * dropdown, so an admin who picks a La Suite set is not sent to NL
		 * Design System docs (#662). A design system with no entry keeps the
		 * link it has.
		 *
		 * @param {string} dsId The design system id.
		 * @spec openspec/specs/admin-settings/spec.md#requirement-documentation-link-follows-the-design-system
		 */
		function updateDocumentationLink(dsId) {
			var link = document.getElementById('nldesign-doc-link')
			var url = designSystemDocs[dsId]
			if (link === null || typeof url !== 'string' || url === '') {
				return
			}
			link.setAttribute('href', url)
		}

		// Update the design system badge for the selected token set
		function updateDesignSystemBadge(tokenSetId) {
			var option = tokenSetSelect
				? tokenSetSelect.querySelector('option[value="' + tokenSetId + '"]')
				: null
			var dsId = option
				? option.getAttribute('data-design-system') || 'nldesign'
				: 'nldesign'
			updateDocumentationLink(dsId)

			var badge = document.getElementById('nldesign-design-system-badge')
			if (!badge) return
			var dsName =
				typeof TT.designSystemLabel === 'function'
					? TT.designSystemLabel(dsId)
					: designSystemNames[dsId] || dsId

			badge.textContent = dsName
			badge.className =
				'nldesign-badge'
				+ (dsId === 'none'
					? ' nldesign-badge--stock'
					: ' nldesign-badge--system')
		}

		// The vocabulary-completeness warning carried on a token set's
		// `warnings` array, or null when the set is complete. Emitted by
		// TokenSetVocabularyAuditService::warningsFor() on the SAME channel as
		// the WCAG contrast warnings, so the two are distinguished by `kind`.
		function incompleteWarningFor(tokenSetId) {
			var ts = tokenSetsData[tokenSetId]
			if (!ts || !ts.warnings) {
				return null
			}
			for (var i = 0; i < ts.warnings.length; i++) {
				if (ts.warnings[i] && ts.warnings[i].kind === 'incomplete') {
					return ts.warnings[i]
				}
			}
			return null
		}

		// How many token names a warning line enumerates before it stops. The
		// counts already carry the magnitude, and a set like `tilburg` declares
		// 119 unread names — enumerating them all turned the apply dialog into a
		// wall of text that pushed the actual token list off screen. The full
		// enumeration lives in `npm run audit:token-sets --verbose`, which is
		// where someone who intends to FIX a set is working anyway; an admin
		// choosing a theme only needs to know that it is incomplete and roughly
		// in what way.
		var INCOMPLETE_SAMPLE_LIMIT = 6

		// Comma-joined sample of a token-name list, ellipsised when truncated.
		// No new translatable string: the "{count}" in the surrounding sentence
		// is the authoritative total, and the ellipsis reads the same in every
		// locale.
		function sampleTokenNames(names) {
			if (names.length <= INCOMPLETE_SAMPLE_LIMIT) {
				return names.join(', ')
			}
			return names.slice(0, INCOMPLETE_SAMPLE_LIMIT).join(', ') + ', …'
		}

		// Human-readable lines describing what an "incomplete" warning found.
		// Shared by the dropdown badge's tooltip and the apply dialog's banner.
		function incompleteWarningLines(warning) {
			var lines = []
			if (warning.missing && warning.missing.length > 0) {
				lines.push(
					t(
						'thematiq',
						'Does not define {count} required token(s): {tokens}',
					)
						.replace('{count}', warning.missing.length)
						.replace('{tokens}', sampleTokenNames(warning.missing)),
				)
			}
			if (warning.foreign && warning.foreign.length > 0) {
				lines.push(
					t(
						'thematiq',
						'Declares {count} --nldesign-* name(s) no stylesheet reads: {tokens}',
					)
						.replace('{count}', warning.foreign.length)
						.replace('{tokens}', sampleTokenNames(warning.foreign)),
				)
			}
			if (warning.primaryMismatch === true) {
				lines.push(
					t(
						'thematiq',
						'Primary color {css} in the token CSS disagrees with {manifest} in the token set manifest.',
					)
						.replace('{css}', warning.cssPrimary || '?')
						.replace('{manifest}', warning.declaredPrimary || '?'),
				)
			}
			return lines
		}

		// Update the "Incomplete set" badge for the selected token set — the
		// third badge state next to the design-system and WCAG badges. Hidden
		// entirely for a complete set, so a clean catalogue stays quiet.
		function updateCompletenessBadge(tokenSetId) {
			var badge = document.getElementById(
				'nldesign-token-set-completeness-badge',
			)
			if (!badge) return

			var warning = incompleteWarningFor(tokenSetId)
			if (warning === null) {
				badge.hidden = true
				badge.textContent = ''
				badge.removeAttribute('title')
				return
			}

			badge.hidden = false
			badge.className = 'nldesign-badge nldesign-badge--warning'
			badge.textContent = t('thematiq', 'Incomplete set')
			badge.setAttribute('title', incompleteWarningLines(warning).join('\n'))
		}

		// Icon-pack indicator (theme-switchable iconography,
		// openspec/specs/icon-packs/spec.md): mirrors design-systems.json's
		// `icon_pack` field for a design system, kept in sync manually (same
		// "inline fallback" pattern as designSystemNames above — the server-
		// rendered value at page load is the source of truth; see
		// lib/Settings/Admin.php::getActiveIconPackIndicator()).
		var designSystemIconPacks = {
			nldesign: ['rvo', 'open-gemeenten', 'den-haag'],
			lasuite: ['dsfr'],
		}

		// Preview what the icon-pack indicator WOULD become for the currently
		// selected (not yet published) token set — a non-authoritative preview,
		// like updateDesignSystemBadge() above. When an admin override is active
		// (appconfig `icon_pack`, server-rendered at page load), the override
		// always wins regardless of the selected token set, so the indicator is
		// left untouched.
		function updateIconPackIndicator(tokenSetId) {
			var indicator = document.getElementById('nldesign-icon-pack-indicator')
			var valueEl = document.getElementById('nldesign-icon-pack-value')
			if (!indicator || !valueEl) return
			if (iconPackSource === 'override') return

			var option = tokenSetSelect
				? tokenSetSelect.querySelector('option[value="' + tokenSetId + '"]')
				: null
			var dsId = option
				? option.getAttribute('data-design-system') || 'nldesign'
				: 'nldesign'
			var packs = designSystemIconPacks[dsId] || []

			valueEl.textContent =
				packs.length > 0
					? packs.join(', ')
					: t('thematiq', 'Nextcloud stock icons (no custom pack)')
		}

		// Show/hide the Marianne (French State typeface) acknowledgement gate —
		// only meaningful for the lasuite design system (openspec/specs/marianne-font/spec.md).
		// Mirrors updateDesignSystemBadge()'s data-design-system lookup so it
		// stays in sync with whichever token set is currently selected, without
		// a page reload.
		function updateMarianneVisibility(tokenSetId) {
			var gate = document.getElementById('nldesign-marianne-gate')
			if (!gate) return

			var option = tokenSetSelect
				? tokenSetSelect.querySelector('option[value="' + tokenSetId + '"]')
				: null
			var dsId = option
				? option.getAttribute('data-design-system') || 'nldesign'
				: 'nldesign'

			gate.style.display = dsId === 'lasuite' ? '' : 'none'
		}

		// Handle token set dropdown selection — open apply dialog first
		if (tokenSetSelect) {
			tokenSetSelect.addEventListener('change', function () {
				var newTokenSet = this.value
				var prevTokenSet =
					this.dataset.previousValue
					|| this.options[
						this.selectedIndex === 0 ? 0 : this.selectedIndex
					].value

				// Store previous value so we can revert on Cancel.
				this.dataset.previousValue = newTokenSet

				// Update preview and design system badge optimistically
				updatePreview(newTokenSet)
				updateDesignSystemBadge(newTokenSet)
				updateCompletenessBadge(newTokenSet)
				updateIconPackIndicator(newTokenSet)
				updateMarianneVisibility(newTokenSet)

				// Open the token overrides apply dialog.
				openTokenSetApplyDialog(newTokenSet, prevTokenSet)
			})

			// Set initial preview for selected item and remember initial value.
			updatePreview(tokenSetSelect.value)
			updateDesignSystemBadge(tokenSetSelect.value)
			updateCompletenessBadge(tokenSetSelect.value)
			updateIconPackIndicator(tokenSetSelect.value)
			updateMarianneVisibility(tokenSetSelect.value)
			tokenSetSelect.dataset.previousValue = tokenSetSelect.value
		}

		/* ==========================================================================
		 * THEME PREVIEW ("proefdraaien") — trial a token set in the admin's own
		 * session before publishing it instance-wide.
		 * ========================================================================== */

		var previewBtn = document.getElementById('nldesign-preview-btn')
		var previewPublishBtn = document.getElementById(
			'nldesign-preview-publish-btn',
		)
		var previewDiscardBtn = document.getElementById(
			'nldesign-preview-discard-btn',
		)
		var currentTokenSetId = loadInitialState('currentTokenSet', '')

		// The preview panel (server-rendered, hidden when no preview is active)
		// is toggled here so starting or discarding a preview needs no reload.
		// The banner on OTHER pages stays server-rendered: it reads the session
		// state on their next load, which is what that state is for.
		var previewPanel = document.getElementById('nldesign-active-preview')

		function beginPreviewOnPage(tokenSetId) {
			var ts = tokenSetsData[tokenSetId]
			activePreview = {
				tokenSet: tokenSetId,
				name: ts && ts.name ? ts.name : tokenSetId,
			}
			if (previewPanel !== null) {
				var status = previewPanel.querySelector('[role="status"]')
				if (status !== null) {
					status.textContent = t(
						'thematiq',
						'Previewing "{name}" in your session only.',
						{ name: activePreview.name },
					)
				}
				previewPanel.style.display = ''
			}
		}

		function endPreviewOnPage() {
			activePreview = null
			if (previewPanel !== null) {
				previewPanel.style.display = 'none'
			}
		}

		// Start a preview of the currently selected token set — session-only,
		// instance-wide token_set is left untouched.
		if (previewBtn !== null && tokenSetSelect !== null) {
			previewBtn.addEventListener('click', function () {
				previewBtn.disabled = true
				var previewed = tokenSetSelect.value
				fetch(OC.generateUrl('/apps/thematiq/settings/preview'), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify({ tokenSet: previewed }),
				})
					.then(function (response) {
						return response.json()
					})
					.then(function (data) {
						if (data.status !== 'ok') {
							previewBtn.disabled = false
							notify(t('thematiq', 'Failed to start theme preview.'))
							return
						}
						return applyLayersFor(previewed).then(function (swapped) {
							previewBtn.disabled = false
							if (swapped !== true) {
								window.location.reload()
								return
							}
							beginPreviewOnPage(previewed)
							notify(t('thematiq', 'Previewing in your session only.'))
						})
					})
					.catch(function (error) {
						previewBtn.disabled = false
						console.error('Error starting theme preview:', error)
						notify(t('thematiq', 'Failed to start theme preview.'))
					})
			})
		}

		// Discard the active preview — clears the session-only state; the panel
		// (and the banner on every other page) reverts to the active set.
		if (previewDiscardBtn !== null) {
			previewDiscardBtn.addEventListener('click', function () {
				previewDiscardBtn.disabled = true
				fetch(OC.generateUrl('/apps/thematiq/settings/preview'), {
					method: 'DELETE',
					headers: { requesttoken: OC.requestToken },
				})
					.then(function () {
						// Back to the instance-wide set on this page.
						return applyLayersFor(currentTokenSetId).then(
							function (swapped) {
								previewDiscardBtn.disabled = false
								if (swapped !== true) {
									window.location.reload()
									return
								}
								endPreviewOnPage()
								reflectSelection(currentTokenSetId)
								notify(t('thematiq', 'Preview discarded.'))
							},
						)
					})
					.catch(function (error) {
						previewDiscardBtn.disabled = false
						console.error('Error discarding theme preview:', error)
						notify(t('thematiq', 'Failed to discard theme preview.'))
					})
			})
		}

		// Publish the active preview — runs the EXISTING apply dialog (and,
		// when applicable, the theming-sync dialog) for the previewed set;
		// only on confirmation does POST /settings/preview/publish fire
		// (publishMode = true), promoting it to the instance-wide active set.
		// Bound whenever the button exists, not only when a preview was active
		// at page load: a preview started on this page (no reload) must be
		// publishable from this page too. `activePreview` is read at click time.
		if (previewPublishBtn !== null) {
			previewPublishBtn.addEventListener('click', function () {
				if (activePreview === null) {
					return
				}
				openTokenSetApplyDialog(
					activePreview.tokenSet,
					currentTokenSetId,
					true,
				)
			})
		}

		// Commit a token set change to the server. In normal mode this is the
		// instance-wide POST /settings/tokenset. In publish mode (promoting an
		// active session preview — "proefdraaien" — to instance-wide) it is
		// POST /settings/preview/publish instead, which reads the previewed set
		// server-side from the caller's own preview state; no body is sent.
		function commitTokenSetChange(tokenSet, publishMode) {
			var url = publishMode
				? OC.generateUrl('/apps/thematiq/settings/preview/publish')
				: OC.generateUrl('/apps/thematiq/settings/tokenset')
			var options = {
				method: 'POST',
				headers: { requesttoken: OC.requestToken },
			}
			if (publishMode !== true) {
				options.headers['Content-Type'] = 'application/json'
				options.body = JSON.stringify({ tokenSet: tokenSet })
			}

			return fetch(url, options).then(function (response) {
				return response.json()
			})
		}

		// Save token set to server (instance-wide, or promoting a preview when
		// publishMode is true).
		function saveTokenSet(tokenSet, publishMode) {
			commitTokenSetChange(tokenSet, publishMode === true)
				.then(function (data) {
					if (data.status !== 'ok') {
						notify(t('thematiq', 'Failed to update theme.'))
						return
					}

					// The set is saved; now put it on THIS page, then offer the
					// core theming sync (colours, logo) as the second step of
					// the same confirmed flow. No reload anywhere.
					return applyLayersFor(tokenSet).then(function (swapped) {
						if (swapped === true) {
							notify(
								publishMode === true
									? t(
											'thematiq',
											'Theme published instance-wide and applied.',
										)
									: t('thematiq', 'Applied.'),
							)
						} else {
							notify(
								publishMode === true
									? t(
											'thematiq',
											'Theme published instance-wide. Reload the page to see changes.',
										)
									: t(
											'thematiq',
											'Theme updated successfully. reload the page to see changes.',
										),
							)
						}

						if (publishMode === true) {
							endPreviewOnPage()
						}

						var tsData = tokenSetsData[tokenSet]
						if (tsData && tsData.theming) {
							checkAndShowThemingDialog(tsData)
						}
					})
				})
				.catch(function (error) {
					console.error('Error saving token set:', error)
					notify(t('thematiq', 'Failed to update theme.'))
				})
		}

		// Fetch current NC theming values and show dialog if they differ
		/**
		 * What syncing core theming to a set would do, computed once and used
		 * by both surfaces: the section inside the apply dialog (the normal
		 * path) and the standalone dialog (when the apply dialog has no token
		 * changes to show). `mode` is `match` (POST the set's values), `reset`
		 * (stock Nextcloud: DELETE, undo everything synced) or `none`.
		 *
		 * Stock is the ABSENCE of a synced theme, not a manifest to match:
		 * matching would keep the previous set's logo (the manifest has none)
		 * and pin a stale primary.
		 */
		/**
		 * The note next to a colour core gets as the blend of a translucent one.
		 *
		 * @param {string|undefined} original The set's own value, when it was translucent.
		 * @return {string} The note, or '' for an opaque colour.
		 */
		function opaqueNote(original) {
			if (!original) {
				return ''
			}
			return t(
				'thematiq',
				"The set says {original}. Nextcloud's own theming has no transparency. It gets this colour instead.",
				{ original: original },
			)
		}

		function computeThemingPlan(tokenSetData, currentTheming) {
			var none = { mode: 'none', diffs: [], payload: null }
			if (!tokenSetData || !currentTheming) {
				return none
			}

			var defaultLabel = t('thematiq', 'Nextcloud default')

			// A stock-based theme resets Nextcloud's branding to its defaults —
			// unless it CAPTURED the branding it was saved with, in which case
			// that is what it brings back, like any other theme.
			var captured = Boolean(
				tokenSetData.theming && tokenSetData.theming.captured === true,
			)
			if (
				(tokenSetData.design_system || 'nldesign') === 'none'
				&& captured === false
			) {
				var resetDiffs = []
				if (currentTheming.primary_color) {
					resetDiffs.push({
						label: t('thematiq', 'Primary color'),
						key: 'primary_color',
						kind: 'color',
						current: currentTheming.primary_color,
						proposed: currentTheming.default_primary_color || '#00679e',
						proposedNote: defaultLabel,
					})
				}
				if (currentTheming.background_color) {
					resetDiffs.push({
						label: t('thematiq', 'Background color'),
						key: 'background_color',
						kind: 'color',
						current: currentTheming.background_color,
						proposed:
							currentTheming.default_background_color || '#00679e',
						proposedNote: defaultLabel,
					})
				}
				if (currentTheming.has_custom_logo === true) {
					resetDiffs.push({
						label: t('thematiq', 'Logo'),
						key: 'logo',
						kind: 'text',
						current: t('thematiq', '(custom logo)'),
						proposed: t('thematiq', 'Nextcloud logo'),
					})
				}
				if (currentTheming.has_custom_background === true) {
					resetDiffs.push({
						label: t('thematiq', 'Background image'),
						key: 'background',
						kind: 'text',
						current: t('thematiq', '(custom)'),
						proposed: defaultLabel,
					})
				}
				if (resetDiffs.length === 0) {
					return none
				}
				return { mode: 'reset', diffs: resetDiffs, payload: null }
			}

			var proposed = tokenSetData.theming
			if (!proposed) {
				return none
			}
			var diffs = []
			var payload = {}

			if (
				proposed.primary_color
				&& proposed.primary_color.toLowerCase()
					!== (currentTheming.primary_color || '').toLowerCase()
			) {
				diffs.push({
					label: t('thematiq', 'Primary color'),
					key: 'primary_color',
					kind: 'color',
					current: currentTheming.primary_color,
					proposed: proposed.primary_color,
					proposedNote: opaqueNote(proposed.primary_color_original),
				})
				payload.primary_color = proposed.primary_color
			}

			if (
				proposed.background_color
				&& proposed.background_color.toLowerCase()
					!== (currentTheming.background_color || '').toLowerCase()
			) {
				diffs.push({
					label: t('thematiq', 'Background color'),
					key: 'background_color',
					kind: 'color',
					current: currentTheming.background_color,
					proposed: proposed.background_color,
					proposedNote: opaqueNote(proposed.background_color_original),
				})
				payload.background_color = proposed.background_color
			}

			// An image is only a difference when the slot does not ALREADY hold
			// this set's file. Core records just that a custom image exists, not
			// which one, so the comparison uses `synced_*` — the path Thematiq
			// itself last applied. Without this the dialog offered the same
			// unchanged logo on every apply and could never stop appearing.
			// (An admin who uploads a logo through core's own panel after a sync
			// leaves `synced_logo` behind; the row is then skipped once, until
			// the next sync rewrites it.)
			if (
				proposed.logo
				&& !(
					currentTheming.has_custom_logo === true
					&& currentTheming.synced_logo === proposed.logo
				)
			) {
				diffs.push({
					label: t('thematiq', 'Logo'),
					key: 'logo',
					kind: 'text',
					current: currentTheming.has_custom_logo
						? t('thematiq', '(custom logo)')
						: t('thematiq', '(default)'),
					proposed: proposed.logo.split('/').pop(),
				})
				payload.logo = proposed.logo
			}

			// The navigation-bar logo and the favicon, compared the same way as
			// the logo: by the file Thematiq last put in the slot.
			;[
				['logoheader', t('thematiq', 'Navigation bar logo')],
				['favicon', t('thematiq', 'Favicon')],
			].forEach(function (slot) {
				var key = slot[0]
				if (
					proposed[key]
					&& !(
						currentTheming['has_custom_' + key] === true
						&& currentTheming['synced_' + key] === proposed[key]
					)
				) {
					diffs.push({
						label: slot[1],
						key: key,
						kind: 'text',
						current: currentTheming['has_custom_' + key]
							? t('thematiq', '(custom)')
							: t('thematiq', '(default)'),
						proposed: proposed[key].split('/').pop(),
					})
					payload[key] = proposed[key]
				}
			})

			if (
				proposed.background
				&& !(
					currentTheming.has_custom_background === true
					&& currentTheming.synced_background === proposed.background
				)
			) {
				diffs.push({
					label: t('thematiq', 'Background image'),
					key: 'background',
					kind: 'text',
					current: currentTheming.has_custom_background
						? t('thematiq', '(custom)')
						: t('thematiq', '(default)'),
					proposed: proposed.background.split('/').pop(),
				})
				payload.background = proposed.background
			}

			// A captured theme also knows whether the background image had been
			// removed ("color") or left as Nextcloud's own ("default"). Offered
			// only when the page is in a different state; an image of the
			// theme's own is the background row above.
			var mode = proposed.background_mode
			var removedNow = currentTheming.background_mime === 'backgroundColor'
			var currentBackground = removedNow
				? t('thematiq', 'Removed (plain color)')
				: currentTheming.has_custom_background
					? t('thematiq', '(custom)')
					: t('thematiq', '(default)')
			if (mode === 'color' && removedNow === false) {
				diffs.push({
					label: t('thematiq', 'Background image'),
					key: 'background_mode',
					kind: 'text',
					current: currentBackground,
					proposed: t('thematiq', 'Removed (plain color)'),
				})
			} else if (
				mode === 'default'
				&& (removedNow === true
					|| currentTheming.has_custom_background === true)
			) {
				diffs.push({
					label: t('thematiq', 'Background image'),
					key: 'background_mode',
					kind: 'text',
					current: currentBackground,
					proposed: t('thematiq', 'Nextcloud default'),
				})
			}

			if (diffs.length === 0) {
				return none
			}

			// Sent with anything that is applied, so a colour change on its own
			// cannot drop the background image the theme was saved with.
			if (mode === 'image' || mode === 'color' || mode === 'default') {
				payload.background_mode = mode
			}

			return { mode: 'match', diffs: diffs, payload: payload }
		}

		/** Execute a plan from computeThemingPlan(); resolves after the page reflects it. */
		function applyThemingPlan(plan) {
			if (!plan || plan.mode === 'none') {
				return Promise.resolve(false)
			}
			// One endpoint for both: `reset=1` undoes everything the sync ever
			// applied. A dedicated route or verb would 404/405 on any instance
			// whose route collection is still cached (an hour), which is what
			// turned a successful switch into "failed to apply".
			var body =
				plan.mode === 'reset'
					? 'reset=1'
					: Object.keys(plan.payload)
							.map(function (key) {
								return (
									encodeURIComponent(key)
									+ '='
									+ encodeURIComponent(plan.payload[key])
								)
							})
							.join('&')

			return fetch(OC.generateUrl('/apps/thematiq/settings/theming'), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					requesttoken: OC.requestToken,
				},
				body: body,
			})
				.then(function (response) {
					return response.json()
				})
				.then(function (data) {
					if (data.status !== 'ok') {
						throw new Error(data.error || 'Theming sync failed')
					}
					return refreshCoreTheming().then(function () {
						return true
					})
				})
		}

		/** One table row per diff, swatches for colours — shared by both surfaces. */
		function themingRowsHtml(diffs) {
			return diffs
				.map(function (diff) {
					var cell = function (value) {
						if (diff.kind === 'color' && value) {
							return (
								'<span class="nldesign-dialog-swatch" style="background:'
								+ escapeHtml(value)
								+ '"></span> '
								+ escapeHtml(value)
							)
						}
						return escapeHtml(value || '')
					}
					return (
						'<tr><td>'
						+ escapeHtml(diff.label)
						+ '</td><td>'
						+ cell(diff.current)
						+ '</td><td>'
						+ cell(diff.proposed)
						+ (diff.proposedNote
							? ' ' + escapeHtml(diff.proposedNote)
							: '')
						+ '</td></tr>'
					)
				})
				.join('')
		}

		function fetchCurrentTheming() {
			return fetch(OC.generateUrl('/apps/thematiq/settings/theming'), {
				headers: { requesttoken: OC.requestToken },
			}).then(function (response) {
				return response.json()
			})
		}

		// Standalone sync dialog — used only when the apply dialog had no token
		// changes to show, so this is still the ONE dialog the admin sees.
		function checkAndShowThemingDialog(tokenSetData) {
			fetchCurrentTheming()
				.then(function (currentTheming) {
					var plan = computeThemingPlan(tokenSetData, currentTheming)
					if (plan.mode === 'reset') {
						showThemingResetDialog(tokenSetData, currentTheming)
					} else if (plan.mode === 'match') {
						showThemingDialog(
							tokenSetData,
							currentTheming,
							tokenSetData.theming,
							plan.diffs,
						)
					}
				})
				.catch(function (error) {
					console.error('Error fetching theming values:', error)
				})
		}

		// Show the theming sync dialog
		// The stock set's version of the sync dialog: "Current" is whatever was
		// synced before, "Proposed" is Nextcloud's own defaults, and confirming
		// calls DELETE /settings/theming, which undoes colours, logo and
		// background the way core's own undo arrows do. Same overlay id as the
		// match dialog, so the accessibility helpers and the specs that look
		// for it keep working.
		function showThemingResetDialog(tokenSetData, currentTheming) {
			var existing = document.getElementById('nldesign-theming-dialog-overlay')
			if (existing) existing.remove()

			var defaultPrimary = currentTheming.default_primary_color || '#00679e'
			var defaultBackground =
				currentTheming.default_background_color || defaultPrimary
			var defaultLogo = OC.imagePath('core', 'logo/logo.svg')
			var currentBg = currentTheming.background_color || defaultBackground
			var currentLogoUrl = currentTheming.logo_url || ''
			var defaultLabel = escapeHtml(t('thematiq', 'Nextcloud default'))

			var rows = ''
			function swatchCell(hex) {
				return (
					'<span class="nldesign-dialog-swatch" style="background:'
					+ escapeHtml(hex)
					+ '"></span> '
					+ escapeHtml(hex)
				)
			}
			if (currentTheming.primary_color) {
				rows +=
					'<tr><td>'
					+ escapeHtml(t('thematiq', 'Primary color'))
					+ '</td><td>'
					+ swatchCell(currentTheming.primary_color)
					+ '</td><td>'
					+ swatchCell(defaultPrimary)
					+ ' '
					+ defaultLabel
					+ '</td></tr>'
			}
			if (currentTheming.background_color) {
				rows +=
					'<tr><td>'
					+ escapeHtml(t('thematiq', 'Background color'))
					+ '</td><td>'
					+ swatchCell(currentTheming.background_color)
					+ '</td><td>'
					+ swatchCell(defaultBackground)
					+ ' '
					+ defaultLabel
					+ '</td></tr>'
			}
			if (currentTheming.has_custom_logo === true) {
				rows +=
					'<tr><td>'
					+ escapeHtml(t('thematiq', 'Logo'))
					+ '</td><td>'
					+ escapeHtml(t('thematiq', '(custom logo)'))
					+ '</td><td>'
					+ escapeHtml(t('thematiq', 'Nextcloud logo'))
					+ '</td></tr>'
			}
			if (currentTheming.has_custom_background === true) {
				rows +=
					'<tr><td>'
					+ escapeHtml(t('thematiq', 'Background image'))
					+ '</td><td>'
					+ escapeHtml(t('thematiq', '(custom)'))
					+ '</td><td>'
					+ defaultLabel
					+ '</td></tr>'
			}

			var dialogHtml =
				''
				+ '<div id="nldesign-theming-dialog-overlay" class="nldesign-dialog-overlay">'
				+ '  <div class="nldesign-dialog">'
				+ '    <h3>'
				+ escapeHtml(
					t('thematiq', 'Reset Nextcloud theming to its defaults?'),
				)
				+ '</h3>'
				+ '    <div class="nldesign-dialog-previews">'
				+ '      <div class="nldesign-dialog-preview-col">'
				+ '        <span class="nldesign-dialog-preview-label">'
				+ escapeHtml(t('thematiq', 'Current'))
				+ '</span>'
				+ '        <div class="nldesign-dialog-preview-box" style="background-color:'
				+ escapeHtml(currentBg)
				+ ';'
				+ (currentTheming.has_custom_background
				&& currentTheming.background_url
					? 'background-image:url('
						+ escapeHtml(currentTheming.background_url)
						+ ');background-size:cover;'
					: '')
				+ '">'
				+ (currentLogoUrl
					? '          <img class="nldesign-dialog-preview-logo" src="'
						+ escapeHtml(currentLogoUrl)
						+ '" alt="'
						+ escapeHtml(t('thematiq', 'Current logo'))
						+ '">'
					: '')
				+ '        </div>'
				+ '      </div>'
				+ '      <div class="nldesign-dialog-preview-col">'
				+ '        <span class="nldesign-dialog-preview-label">'
				+ escapeHtml(t('thematiq', 'Proposed'))
				+ '</span>'
				+ '        <div class="nldesign-dialog-preview-box" style="background-color:'
				+ escapeHtml(defaultBackground)
				+ ';">'
				+ '          <img class="nldesign-dialog-preview-logo" src="'
				+ escapeHtml(defaultLogo)
				+ '" alt="'
				+ escapeHtml(t('thematiq', 'Nextcloud logo'))
				+ '">'
				+ '        </div>'
				+ '      </div>'
				+ '    </div>'
				+ '    <table class="nldesign-dialog-table">'
				+ '      <thead><tr><th>'
				+ escapeHtml(t('thematiq', 'Setting'))
				+ '</th><th>'
				+ escapeHtml(t('thematiq', 'Current'))
				+ '</th><th>'
				+ escapeHtml(t('thematiq', 'Proposed'))
				+ '</th></tr></thead>'
				+ '      <tbody>'
				+ rows
				+ '</tbody>'
				+ '    </table>'
				+ '    <p class="nldesign-dialog-hint">'
				+ escapeHtml(
					t(
						'thematiq',
						"Stock Nextcloud means Nextcloud's own colors and logo. Everything Thematiq synced into Nextcloud theming is undone; the token set itself is already applied.",
					),
				)
				+ '</p>'
				+ '    <div class="nldesign-dialog-actions">'
				+ '      <button class="nldesign-dialog-cancel">'
				+ escapeHtml(t('thematiq', 'Cancel'))
				+ '</button>'
				+ '      <button class="nldesign-dialog-confirm">'
				+ escapeHtml(t('thematiq', 'Reset theming'))
				+ '</button>'
				+ '    </div>'
				+ '  </div>'
				+ '</div>'

			document.body.insertAdjacentHTML('beforeend', dialogHtml)
			var overlay = document.getElementById('nldesign-theming-dialog-overlay')

			makeDialogAccessible(overlay, function () {
				closeDialogOverlay(overlay)
			})
			overlay
				.querySelector('.nldesign-dialog-cancel')
				.addEventListener('click', function () {
					closeDialogOverlay(overlay)
				})
			overlay.addEventListener('click', function (e) {
				if (e.target === overlay) {
					closeDialogOverlay(overlay)
				}
			})

			overlay
				.querySelector('.nldesign-dialog-confirm')
				.addEventListener('click', function () {
					var btn = this
					btn.disabled = true
					btn.textContent = t('thematiq', 'Resetting…')

					applyThemingPlan({ mode: 'reset', diffs: [], payload: null })
						.then(function () {
							closeDialogOverlay(overlay)
							notify(
								t(
									'thematiq',
									'Nextcloud theming reset to its defaults.',
								),
							)
						})
						.catch(function (error) {
							closeDialogOverlay(overlay)
							console.error('Error resetting theming:', error)
							notify(
								t('thematiq', 'Failed to reset Nextcloud theming.'),
							)
						})
				})
		}

		function showThemingDialog(tokenSetData, currentTheming, proposed, diffs) {
			// Remove any existing dialog
			var existing = document.getElementById('nldesign-theming-dialog-overlay')
			if (existing) existing.remove()

			var tokenSetName = tokenSetData.name

			// Build comparison rows
			var rows = ''
			diffs.forEach(function (diff) {
				var currentDisplay = diff.current || ''
				var proposedDisplay = diff.proposed || ''

				if (
					diff.key === 'primary_color'
					|| diff.key === 'background_color'
				) {
					currentDisplay =
						'<span class="nldesign-dialog-swatch" style="background:'
						+ escapeHtml(diff.current)
						+ '"></span> '
						+ escapeHtml(diff.current)
					proposedDisplay =
						'<span class="nldesign-dialog-swatch" style="background:'
						+ escapeHtml(diff.proposed)
						+ '"></span> '
						+ escapeHtml(diff.proposed)
				} else {
					currentDisplay = escapeHtml(currentDisplay)
					proposedDisplay = escapeHtml(proposedDisplay)
				}

				rows +=
					'<tr>'
					+ '<td>'
					+ escapeHtml(diff.label)
					+ '</td>'
					+ '<td>'
					+ currentDisplay
					+ '</td>'
					+ '<td>'
					+ proposedDisplay
					+ (diff.proposedNote ? ' ' + escapeHtml(diff.proposedNote) : '')
					+ '</td>'
					+ '</tr>'
			})

			// Build preview boxes
			var currentBg = currentTheming.background_color || '#0082c9'
			var proposedBg = proposed.background_color || currentBg
			var currentLogoUrl = currentTheming.logo_url || ''
			var proposedLogoPath = proposed.logo
				? OC.linkTo('thematiq', proposed.logo)
				: ''

			// Dark logo row — informational only. logo_dark is never sent to
			// Nextcloud core theming (core has a single logo slot — the open
			// upstream request is nextcloud/server#47357); nldesign's own
			// generated dark stylesheet applies it via --nldesign-logo-url.
			var darkLogoRow = ''
			if (proposed.logo_dark) {
				var darkLogoPath = OC.linkTo('thematiq', proposed.logo_dark)
				darkLogoRow =
					''
					+ '    <div class="nldesign-dialog-dark-logo-row">'
					+ '      <div class="nldesign-dialog-preview-box nldesign-dialog-preview-box--dark">'
					+ '        <img class="nldesign-dialog-preview-logo" src="'
					+ escapeHtml(darkLogoPath)
					+ '" alt="'
					+ escapeHtml(t('thematiq', 'Dark logo'))
					+ '">'
					+ '      </div>'
					+ '      <p class="nldesign-dialog-hint">'
					+ escapeHtml(
						t(
							'thematiq',
							"This token set also ships a dark-surface logo. Nextcloud core has no dark logo slot, so it is applied by nldesign's own dark-mode stylesheet, not synced to Nextcloud theming.",
						),
					)
					+ '</p>'
					+ '    </div>'
			}

			var dialogHtml =
				''
				+ '<div id="nldesign-theming-dialog-overlay" class="nldesign-dialog-overlay">'
				+ '  <div class="nldesign-dialog">'
				+ '    <h3>'
				+ escapeHtml(
					t(
						'thematiq',
						'Update Nextcloud theming to match {name}?',
					).replace('{name}', tokenSetName),
				)
				+ '</h3>'
				+ '    <div class="nldesign-dialog-previews">'
				+ '      <div class="nldesign-dialog-preview-col">'
				+ '        <span class="nldesign-dialog-preview-label">'
				+ escapeHtml(t('thematiq', 'Current'))
				+ '</span>'
				+ '        <div class="nldesign-dialog-preview-box" style="background-color:'
				+ escapeHtml(currentBg)
				+ ';'
				+ (currentTheming.has_custom_background
				&& currentTheming.background_url
					? 'background-image:url('
						+ escapeHtml(currentTheming.background_url)
						+ ');background-size:cover;'
					: '')
				+ '">'
				+ (currentLogoUrl
					? '          <img class="nldesign-dialog-preview-logo" src="'
						+ escapeHtml(currentLogoUrl)
						+ '" alt="Current logo">'
					: '')
				+ '        </div>'
				+ '      </div>'
				+ '      <div class="nldesign-dialog-preview-col">'
				+ '        <span class="nldesign-dialog-preview-label">'
				+ escapeHtml(t('thematiq', 'Proposed'))
				+ '</span>'
				+ '        <div class="nldesign-dialog-preview-box" style="background-color:'
				+ escapeHtml(proposedBg)
				+ ';">'
				+ (proposedLogoPath
					? '          <img class="nldesign-dialog-preview-logo" src="'
						+ escapeHtml(proposedLogoPath)
						+ '" alt="Proposed logo">'
					: currentLogoUrl
						? '          <img class="nldesign-dialog-preview-logo" src="'
							+ escapeHtml(currentLogoUrl)
							+ '" alt="Current logo">'
						: '')
				+ '        </div>'
				+ '      </div>'
				+ '    </div>'
				+ '    <table class="nldesign-dialog-table">'
				+ '      <thead><tr><th>'
				+ escapeHtml(t('thematiq', 'Setting'))
				+ '</th><th>'
				+ escapeHtml(t('thematiq', 'Current'))
				+ '</th><th>'
				+ escapeHtml(t('thematiq', 'Proposed'))
				+ '</th></tr></thead>'
				+ '      <tbody>'
				+ rows
				+ '</tbody>'
				+ '    </table>'
				+ '    <p class="nldesign-dialog-hint">'
				+ escapeHtml(
					t(
						'thematiq',
						'Only values that differ are shown. items without a proposed value are left unchanged.',
					),
				)
				+ '</p>'
				+ darkLogoRow
				+ '    <div class="nldesign-dialog-actions">'
				+ '      <button class="nldesign-dialog-cancel">'
				+ escapeHtml(t('thematiq', 'Cancel'))
				+ '</button>'
				+ '      <button class="nldesign-dialog-confirm">'
				+ escapeHtml(t('thematiq', 'Update theming'))
				+ '</button>'
				+ '    </div>'
				+ '  </div>'
				+ '</div>'

			document.body.insertAdjacentHTML('beforeend', dialogHtml)

			var overlay = document.getElementById('nldesign-theming-dialog-overlay')

			makeDialogAccessible(overlay, function () {
				closeDialogOverlay(overlay)
			})

			// Cancel button
			overlay
				.querySelector('.nldesign-dialog-cancel')
				.addEventListener('click', function () {
					closeDialogOverlay(overlay)
				})

			// Close on overlay click
			overlay.addEventListener('click', function (e) {
				if (e.target === overlay) {
					closeDialogOverlay(overlay)
				}
			})

			// Confirm button
			overlay
				.querySelector('.nldesign-dialog-confirm')
				.addEventListener('click', function () {
					var btn = this
					btn.disabled = true
					btn.textContent = t('thematiq', 'Updating...')

					var payload = {}
					diffs.forEach(function (diff) {
						if (
							diff.key === 'primary_color'
							|| diff.key === 'background_color'
						) {
							payload[diff.key] = diff.proposed
						} else if (
							diff.key === 'logo'
							|| diff.key === 'logoheader'
							|| diff.key === 'favicon'
							|| diff.key === 'background'
						) {
							payload[diff.key] = proposed[diff.key]
						}
					})
					// A theme that captured Nextcloud's branding says which
					// background state it was saved in; see computeThemingPlan().
					if (
						Object.keys(payload).length > 0
						|| diffs.some(function (diff) {
							return diff.key === 'background_mode'
						})
					) {
						if (proposed.background_mode) {
							payload.background_mode = proposed.background_mode
						}
					}

					var url = OC.generateUrl('/apps/thematiq/settings/theming')
					fetch(url, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
							requesttoken: OC.requestToken,
						},
						body: Object.keys(payload)
							.map(function (key) {
								return (
									encodeURIComponent(key)
									+ '='
									+ encodeURIComponent(payload[key])
								)
							})
							.join('&'),
					})
						.then(function (response) {
							return response.json()
						})
						.then(function (data) {
							closeDialogOverlay(overlay)
							if (data.status === 'ok') {
								// Core regenerates its theme stylesheets from the new
								// values; re-request them and update core's own panel
								// fields on this page. No reload.
								return refreshCoreTheming().then(function () {
									notify(
										t('thematiq', 'Nextcloud theming updated.'),
									)
								})
							} else {
								notify(
									t(
										'thematiq',
										'Failed to update Nextcloud theming:',
									) + (data.error || ''),
								)
							}
						})
						.catch(function (error) {
							closeDialogOverlay(overlay)
							console.error('Error updating theming:', error)
							notify(
								t('thematiq', 'Failed to update Nextcloud theming.'),
							)
						})
				})
		}

		/**
		 * Escape a value for HTML text and for a quoted attribute value.
		 *
		 * Most call sites put the result inside value="..." or title="...", so
		 * quotes must be escaped too. The old textContent/innerHTML round trip
		 * left `"` as is, and a quote in an imported overrides value closed
		 * the attribute and let the rest parse as new attributes (#622).
		 *
		 * @param {*} text The value to escape.
		 * @return {string} The escaped value.
		 */
		function escapeHtml(text) {
			return String(text === null || text === undefined ? '' : text)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#39;')
		}

		/* ==========================================================================
		 * DIALOG KEYBOARD ACCESSIBILITY
		 *
		 * The custom overlay dialogs (theming sync, token-set apply) are hand-rolled
		 * markup, not <dialog> elements, so none of the WAI-ARIA Dialog (Modal)
		 * pattern behaviour comes for free. Without this, keyboard-only users could
		 * not close a dialog without a mouse (no Escape handling), Tab could leave
		 * focus behind the overlay (no focus trap — WCAG 2.4.3 Focus Order), and
		 * focus was never moved into the dialog on open or restored to the
		 * triggering control on close (WCAG 2.1.1 Keyboard / 2.4.3).
		 * ========================================================================== */

		/**
		 * Return the currently visible, non-disabled focusable elements inside a
		 * container, in DOM order.
		 *
		 * Visibility is checked via computed `display`/`visibility` rather than
		 * `offsetParent` — `offsetParent` is also null for `position: fixed`
		 * elements even when they are visible, which would wrongly exclude a
		 * fixed-position dialog's own controls from the focus trap.
		 */
		function getFocusableElements(container) {
			var candidates = container.querySelectorAll(
				'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
			)
			return Array.prototype.filter.call(candidates, function (el) {
				if (el.disabled === true) {
					return false
				}
				var style = window.getComputedStyle(el)
				return style.display !== 'none' && style.visibility !== 'hidden'
			})
		}

		/**
		 * Wire a hand-rolled overlay dialog for keyboard use: WAI-ARIA `dialog`
		 * role, focus moved to the first focusable control on open, Tab/Shift+Tab
		 * trapped within the dialog, Escape invoking the caller's close handler,
		 * and focus restored to the element that had focus before the dialog
		 * opened once it closes.
		 *
		 * @param {HTMLElement} overlay The `.nldesign-dialog-overlay` element already inserted into the DOM.
		 * @param {Function}    closeFn Called when Escape is pressed; must remove the overlay (e.g. via closeDialogOverlay()).
		 */
		function makeDialogAccessible(overlay, closeFn) {
			var dialogEl = overlay.querySelector('.nldesign-dialog')
			if (dialogEl === null) {
				return
			}

			var titleEl = dialogEl.querySelector('h3')
			if (titleEl !== null) {
				if (!titleEl.id) {
					titleEl.id =
						'nldesign-dialog-title-'
						+ Math.random().toString(36).slice(2)
				}
				dialogEl.setAttribute('aria-labelledby', titleEl.id)
			}
			dialogEl.setAttribute('role', 'dialog')
			dialogEl.setAttribute('aria-modal', 'true')
			if (!dialogEl.hasAttribute('tabindex')) {
				dialogEl.setAttribute('tabindex', '-1')
			}

			var previouslyFocused = document.activeElement
			var initialFocusable = getFocusableElements(dialogEl)
			;(initialFocusable[0] || dialogEl).focus()

			function keydownHandler(e) {
				if (e.key === 'Escape' || e.keyCode === 27) {
					e.preventDefault()
					closeFn()
					return
				}
				if (e.key === 'Tab' || e.keyCode === 9) {
					var items = getFocusableElements(dialogEl)
					if (items.length === 0) {
						return
					}
					var first = items[0]
					var last = items[items.length - 1]
					if (e.shiftKey && document.activeElement === first) {
						e.preventDefault()
						last.focus()
					} else if (!e.shiftKey && document.activeElement === last) {
						e.preventDefault()
						first.focus()
					}
				}
			}

			overlay.addEventListener('keydown', keydownHandler)

			// Teardown hook consumed by closeDialogOverlay() so every dialog-close
			// path (Cancel, Escape, click-outside, successful submit) restores
			// focus consistently.
			overlay.nldesignA11yCleanup = function () {
				overlay.removeEventListener('keydown', keydownHandler)
				if (
					previouslyFocused !== null
					&& typeof previouslyFocused.focus === 'function'
				) {
					previouslyFocused.focus()
				}
			}
		}

		/**
		 * Remove a hand-rolled overlay dialog from the DOM, running its
		 * accessibility cleanup (focus restore) first. Safe to call more than
		 * once or with an already-detached overlay.
		 */
		function closeDialogOverlay(overlay) {
			if (overlay === null || overlay === undefined) {
				return
			}
			if (typeof overlay.nldesignA11yCleanup === 'function') {
				overlay.nldesignA11yCleanup()
				overlay.nldesignA11yCleanup = null
			}
			overlay.remove()
		}

		// Handle hide slogan checkbox
		if (hideSloganCheckbox) {
			hideSloganCheckbox.addEventListener('change', function () {
				var hideSlogan = this.checked
				saveSloganSetting(hideSlogan)
			})
		}

		// Handle the "primary drives every component" checkbox
		if (primaryDrivesComponentsCheckbox) {
			primaryDrivesComponentsCheckbox.addEventListener('change', function () {
				savePrimaryDrivesComponentsSetting(this.checked)
			})
		}

		// Handle show menu labels checkbox
		var showMenuLabelsCheckbox = document.getElementById(
			'nldesign-show-menu-labels',
		)
		if (showMenuLabelsCheckbox) {
			showMenuLabelsCheckbox.addEventListener('change', function () {
				var showMenuLabels = this.checked
				saveMenuLabelsSetting(showMenuLabels)
			})
		}

		// Handle dark mode variants checkbox — instance-wide toggle only; never
		// touches the Nextcloud theme choice itself (openspec/specs/dark-mode/spec.md).
		var darkVariantsCheckbox = document.getElementById('nldesign-dark-variants')
		if (darkVariantsCheckbox) {
			darkVariantsCheckbox.addEventListener('change', function () {
				saveDarkVariantsSetting(this.checked)
			})
		}

		// Save dark-mode variants toggle to server
		function saveDarkVariantsSetting(enabled) {
			var url = OC.generateUrl('/apps/thematiq/settings/dark-variants')

			fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({ enabled: enabled }),
			})
				.then(function (response) {
					return response.json()
				})
				.then(function (data) {
					if (data.status === 'ok') {
						// The dark-variant layer is part of the set's manifest and
						// the manifest reads this toggle: re-applying the current set
						// adds or drops that one stylesheet.
						return applyLayersFor(pageTokenSetId).then(
							function (swapped) {
								notify(
									swapped === true
										? t('thematiq', 'Applied.')
										: t(
												'thematiq',
												'Setting saved successfully. reload the page to see changes.',
											),
								)
							},
						)
					} else {
						notify(t('thematiq', 'Failed to save setting.'))
					}
				})
				.catch(function (error) {
					console.error('Error saving dark variants setting:', error)
					notify(t('thematiq', 'Failed to save setting.'))
				})
		}

		// Handle the Marianne (French State typeface) acknowledgement checkbox —
		// ticking it is the operator's affirmation of French-state eligibility
		// (openspec/specs/marianne-font/spec.md, AGREEMENT-MARIANNE.md).
		var marianneCheckbox = document.getElementById('nldesign-marianne-enabled')
		if (marianneCheckbox) {
			marianneCheckbox.addEventListener('change', function () {
				saveMarianneSetting(this.checked)
			})
		}

		// Save the Marianne acknowledgement gate to server
		function saveMarianneSetting(enabled) {
			var url = OC.generateUrl('/apps/thematiq/settings/marianne')

			fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({ enabled: enabled }),
			})
				.then(function (response) {
					return response.json()
				})
				.then(function (data) {
					if (data.status === 'ok') {
						// The Marianne layer is gated in the set's manifest by this
						// toggle: re-applying the current set adds or drops it.
						return applyLayersFor(pageTokenSetId).then(
							function (swapped) {
								notify(
									swapped === true
										? t('thematiq', 'Applied.')
										: t(
												'thematiq',
												'Setting saved successfully. reload the page to see changes.',
											),
								)
							},
						)
					} else {
						notify(t('thematiq', 'Failed to save setting.'))
					}
				})
				.catch(function (error) {
					console.error('Error saving Marianne setting:', error)
					notify(t('thematiq', 'Failed to save setting.'))
				})
		}

		// Save hide slogan setting to server
		function saveSloganSetting(hideSlogan) {
			var url = OC.generateUrl('/apps/thematiq/settings/slogan')

			fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({ hideSlogan: hideSlogan }),
			})
				.then(function (response) {
					return response.json()
				})
				.then(function (data) {
					if (data.status === 'ok') {
						// hide-slogan.css only paints the login page, but it is on this
						// page's cascade too: add or drop it so the state is true here
						// as well, and say where it shows.
						setConditionalLayer('hide-slogan', hideSlogan === true)
						notify(t('thematiq', 'Applied. Visible on the login page.'))
					} else {
						notify(t('thematiq', 'Failed to save setting.'))
					}
				})
				.catch(function (error) {
					console.error('Error saving slogan setting:', error)
					notify(t('thematiq', 'Failed to save setting.'))
				})
		}

		// Save the "primary drives every component" setting to the server, then
		// bring this page into the state it just asked for: add or drop
		// primary-lock.css so the specimens and the playground repaint, and
		// re-render the editor so the rows the primary now owns lock or unlock.
		function savePrimaryDrivesComponentsSetting(enabled) {
			var url = OC.generateUrl(
				'/apps/thematiq/settings/primary-drives-components',
			)

			fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({ primaryDrivesComponents: enabled }),
			})
				.then(function (response) {
					return response.json()
				})
				.then(function (data) {
					if (data.status === 'ok') {
						primaryDrivesComponents = enabled === true
						setConditionalLayer('primary-lock', primaryDrivesComponents)
						refreshTokenEditorLocks()
						notify(t('thematiq', 'Applied.'))
					} else {
						notify(t('thematiq', 'Failed to save setting.'))
					}
				})
				.catch(function (error) {
					console.error(
						'Error saving primary-drives-components setting:',
						error,
					)
					notify(t('thematiq', 'Failed to save setting.'))
				})
		}

		/**
		 * Lock or unlock the editor rows the primary owns, in place.
		 *
		 * Deliberately not a re-render: the editor holds unsaved edits in
		 * `tokenEditorState`, and rebuilding the panels would drop them and reset
		 * the open tab. Only the two attributes that express the lock are touched.
		 *
		 * @return {void}
		 */
		function refreshTokenEditorLocks() {
			var container = document.getElementById('nldesign-token-editor')
			if (container === null) {
				return
			}

			container
				.querySelectorAll('.nldesign-token-row')
				.forEach(function (row) {
					var locked = isTokenLocked(row.dataset.tokenRow)
					var reason = lockReason(row.dataset.tokenRow)
					row.classList.toggle('nldesign-token-row--locked', locked)
					row.querySelectorAll('input').forEach(function (input) {
						input.disabled = locked
						if (reason === '') {
							input.removeAttribute('title')
						} else {
							input.setAttribute('title', reason)
						}
					})
					// The reset button too: resetting a locked row changes it.
					var reset = row.querySelector('.nldesign-reset-btn')
					if (reset !== null) {
						reset.disabled = locked
					}
				})
		}

		// Save show menu labels setting to server.
		function saveMenuLabelsSetting(showMenuLabels) {
			var url = OC.generateUrl('/apps/thematiq/settings/menulabels')

			fetch(url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({ showMenuLabels: showMenuLabels }),
			})
				.then(function (response) {
					return response.json()
				})
				.then(function (data) {
					if (data.status === 'ok') {
						setConditionalLayer(
							'show-menu-labels',
							showMenuLabels === true,
						)
						notify(t('thematiq', 'Applied.'))
					} else {
						notify(t('thematiq', 'Failed to save setting.'))
					}
				})
				.catch(function (error) {
					console.error('Error saving menu labels setting:', error)
					notify(t('thematiq', 'Failed to save setting.'))
				})
		}

		/* ==========================================================================
		 * TOKEN EDITOR PANEL
		 * ========================================================================== */

		// Holds the in-memory state of the editor: token name → { resolved, custom, current, isDirty }
		var tokenEditorState = {}

		/**
		 * The administrator's own dark value per colour token, and the value the
		 * server derives when there is none (the field's placeholder).
		 */
		var tokenEditorDark = {}
		var tokenEditorDarkDerived = {}
		// Registry from server: token name → { tab, type, label }
		var tokenRegistry = {}
		// Tab labels from server: tab id → display label
		var tokenTabLabels = {}

		/**
		 * The value a token row should show.
		 *
		 * A component token is deliberately UNDECLARED until somebody sets one:
		 * that is what makes `var(--nldesign-component-x, var(--global))` in
		 * css/component-scopes.css fall through to the brand value, and what
		 * makes an untouched instance render exactly as it did before the
		 * component layer existed. The cost is that `getPropertyValue` reports
		 * it as the empty string, so every one of those rows opened blank —
		 * a white swatch beside a component that is plainly not white.
		 *
		 * So when the token itself is unset, the row shows the Nextcloud
		 * variable it falls back to: the colour the component is ACTUALLY
		 * wearing. The registry carries that name as `global`.
		 *
		 * This is display only. `saveOverrides()` writes a token only when its
		 * value differs from `resolved`, and `resolved` is what this returned —
		 * so showing the inherited colour never writes it, and the fallback
		 * stays live until the admin actually picks something.
		 *
		 * The FALLBACK is read off `<body>` first, for the same reason
		 * css/component-scopes.css captures it there: the design systems declare
		 * the Nextcloud globals on `body` and Nextcloud core declares its own on
		 * `:root`. Reading the global off the document element returned CORE's
		 * colour, so a row for a token nobody had set opened showing Nextcloud
		 * blue beside a component wearing the brand colour.
		 *
		 * The token's OWN value is still read off `:root` — that is where
		 * defaults.css and custom-overrides.css declare `--nldesign-*`.
		 *
		 * @param {CSSStyleDeclaration} rootStyle Computed style of the document element.
		 * @param {CSSStyleDeclaration} bodyStyle Computed style of the body element.
		 * @param {string} name The CSS custom property name.
		 *
		 * @return {string} The resolved value, or its fallback, or ''.
		 */
		function resolveTokenValue(rootStyle, bodyStyle, name) {
			var own = rootStyle.getPropertyValue(name).trim()
			if (own !== '') {
				return own
			}

			var meta = tokenRegistry[name]
			if (meta === undefined || !meta.global) {
				return ''
			}

			var inherited = bodyStyle.getPropertyValue(meta.global).trim()
			if (inherited !== '') {
				return inherited
			}

			return rootStyle.getPropertyValue(meta.global).trim()
		}

		/**
		 * Initialise and mount the token editor panel into #nldesign-token-editor.
		 */
		function initTokenEditor() {
			var container = document.getElementById('nldesign-token-editor')
			if (container === null) {
				return
			}

			fetch(overridesUrl('', editedTokenSetId()), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					// Both registries: the one the discarded rows were drawn
					// from and the one the new rows are drawn from.
					clearLiveTokens(
						Object.keys(tokenRegistry).concat(
							Object.keys(data.registry || {}),
						),
					)
					tokenRegistry = data.registry || {}
					tokenTabLabels = data.tabs || {}
					var overrides = data.overrides || {}
					tokenEditorDark = Object.assign({}, data.darkOverrides || {})
					tokenEditorDarkDerived = data.darkDerived || {}
					// Read resolved values from the live CSS stack.
					var rootStyle = getComputedStyle(document.documentElement)
					var bodyStyle = getComputedStyle(document.body)
					Object.keys(tokenRegistry).forEach(function (name) {
						var resolved = resolveTokenValue(rootStyle, bodyStyle, name)
						var overridden =
							overrides[name] !== undefined ? overrides[name] : null
						tokenEditorState[name] = {
							resolved: resolved,
							custom: overridden,
							current: overridden !== null ? overridden : resolved,
							// What the reset button goes back to: the value as it
							// was last saved, not the theme's own.
							saved: overridden !== null ? overridden : resolved,
							isDirty: false,
						}
					})

					renderTokenEditor(container, overrides)
				})
				.catch(function (err) {
					console.error('Failed to load token editor:', err)
					container.innerHTML =
						'<p class="settings-hint">'
						+ escapeHtml(t('thematiq', 'Could not load token editor.'))
						+ '</p>'
				})
		}

		/**
		 * Render the full token editor panel into the given container element.
		 */
		function renderTokenEditor(container, overrides) {
			var grouped = {}
			Object.keys(tokenRegistry).forEach(function (name) {
				var meta = tokenRegistry[name]
				if (grouped[meta.tab] === undefined) {
					grouped[meta.tab] = []
				}
				grouped[meta.tab].push(name)
			})

			var tabOrder = ['login', 'content', 'status', 'typography']
			var tabsHtml = ''
			var panelsHtml = ''
			var isFirst = true

			tabOrder.forEach(function (tabId) {
				if (grouped[tabId] === undefined) {
					return
				}
				var label = escapeHtml(tokenTabLabels[tabId] || tabId)
				var activeClass = isFirst ? ' active' : ''
				tabsHtml +=
					'<button class="nldesign-tab-btn'
					+ activeClass
					+ '" data-tab="'
					+ escapeHtml(tabId)
					+ '">'
					+ label
					+ '</button>'
				var rowsHtml = ''
				grouped[tabId].forEach(function (name) {
					rowsHtml += buildTokenRow(
						name,
						overrides[name] !== undefined ? overrides[name] : null,
					)
				})
				panelsHtml +=
					'<div class="nldesign-tab-panel'
					+ activeClass
					+ '" data-panel="'
					+ escapeHtml(tabId)
					+ '">'
					+ rowsHtml
					+ '</div>'
				isFirst = false
			})

			container.innerHTML =
				''
				+ '<div class="nldesign-token-editor">'
				+ '<div class="nldesign-token-editor-header">'
				+ '<h3>'
				+ escapeHtml(t('thematiq', 'Custom token overrides'))
				+ '</h3>'
				+ '<div class="nldesign-token-editor-actions">'
				+ '<button type="button" class="nldesign-btn nldesign-btn--small" id="nldesign-export-btn">'
				+ escapeHtml(t('thematiq', 'Download'))
				+ '</button>'
				// A real button, so it is drawn like the ones beside it; the file
				// input stays hidden and is only opened once the dialog is read.
				+ '<button type="button" class="nldesign-btn nldesign-btn--small" id="nldesign-import-btn">'
				+ escapeHtml(t('thematiq', 'Upload'))
				+ '</button>'
				+ '<input type="file" id="nldesign-import-input" accept=".css" style="display:none" aria-label="'
				+ escapeHtml(t('thematiq', 'Overrides file to upload (CSS)'))
				+ '">'
				+ '</div>'
				+ '</div>'
				+ '<div class="nldesign-tabs">'
				+ tabsHtml
				+ '</div>'
				+ panelsHtml
				+ '<div class="nldesign-save-bar">'
				+ '<span class="nldesign-save-status" id="nldesign-save-status"></span>'
				+ '<button class="nldesign-btn nldesign-btn--primary" id="nldesign-save-btn">'
				+ escapeHtml(t('thematiq', 'Save overrides'))
				+ '</button>'
				+ '</div>'
				+ '</div>'
				// The way back. "Do not ask again" lives inside a dialog the
				// admin has just dismissed, so without these two the offer to
				// keep a set of changes as its own theme would be gone for good
				// after one careless click. Worded the same way round as that
				// dialog: ticked means "stop asking".
				+ '<div class="nldesign-confirm-controls">'
				// Nextcloud's own base tokens stay locked until asked for; see
				// baseTokensUnlocked and confirmBaseUnlock(). With the editor's
				// other settings, as a checkbox like them.
				+ '<div class="nldesign-base-unlock">'
				+ '<input type="checkbox" class="checkbox" id="nldesign-base-unlock"'
				+ (baseTokensUnlocked === true ? ' checked' : '')
				+ '>'
				+ '<label for="nldesign-base-unlock">'
				+ escapeHtml(t('thematiq', "Also edit Nextcloud's base tokens"))
				+ '</label>'
				+ '</div>'
				+ '<div>'
				+ '<input type="checkbox" class="checkbox" id="nldesign-confirm-save-stock">'
				+ '<label for="nldesign-confirm-save-stock">'
				+ escapeHtml(
					t(
						'thematiq',
						"Don't ask before saving while the stock Nextcloud theme is active",
					),
				)
				+ '</label>'
				+ '</div>'
				+ '<div>'
				+ '<input type="checkbox" class="checkbox" id="nldesign-confirm-save-theme">'
				+ '<label for="nldesign-confirm-save-theme">'
				+ escapeHtml(
					t(
						'thematiq',
						"Don't ask before saving while a token set is active",
					),
				)
				+ '</label>'
				+ '</div>'
				+ '</div>'
				+ '<div id="nldesign-import-result" class="nldesign-import-result" style="display:none"></div>'

			container.querySelectorAll('.nldesign-tab-btn').forEach(function (btn) {
				btn.addEventListener('click', function () {
					container
						.querySelectorAll('.nldesign-tab-btn')
						.forEach(function (b) {
							b.classList.remove('active')
						})
					container
						.querySelectorAll('.nldesign-tab-panel')
						.forEach(function (p) {
							p.classList.remove('active')
						})
					btn.classList.add('active')
					container
						.querySelector(
							'.nldesign-tab-panel[data-panel="'
								+ btn.dataset.tab
								+ '"]',
						)
						.classList.add('active')
				})
			})

			wireTokenRows(container)

			wireConfirmControls()

			wireBaseUnlock()

			document
				.getElementById('nldesign-save-btn')
				.addEventListener('click', saveOverrides)
			document
				.getElementById('nldesign-export-btn')
				.addEventListener('click', confirmExportOverrides)
			document
				.getElementById('nldesign-import-btn')
				.addEventListener('click', confirmImportOverrides)
			document
				.getElementById('nldesign-import-input')
				.addEventListener('change', function (e) {
					var file = e.target.files[0]
					if (file === undefined) {
						return
					}
					importOverrides(file)
					e.target.value = ''
				})
		}

		/**
		 * Wire the "also edit Nextcloud's base tokens" toggle.
		 *
		 * Unticking locks the base rows again straight away. Ticking asks
		 * first, and the box only stays ticked when the admin confirms.
		 *
		 * @return {void}
		 */
		function wireBaseUnlock() {
			var box = document.getElementById('nldesign-base-unlock')
			if (box === null) {
				return
			}

			box.addEventListener('change', function () {
				if (box.checked === false) {
					baseTokensUnlocked = false
					refreshTokenEditorLocks()
					return
				}
				box.checked = false
				confirmBaseUnlock(function () {
					box.checked = true
					baseTokensUnlocked = true
					refreshTokenEditorLocks()
				})
			})
		}

		/**
		 * Warn before the base tokens are unlocked.
		 *
		 * @param {Function} proceed Called when the admin confirms.
		 *
		 * @return {void}
		 */
		function confirmBaseUnlock(proceed) {
			var html =
				'<div class="nldesign-dialog-overlay" id="nldesign-base-unlock-overlay">'
				+ '<div class="nldesign-dialog nldesign-dialog--small">'
				+ '<h3>'
				+ escapeHtml(t('thematiq', "Edit Nextcloud's base tokens?"))
				+ '</h3>'
				+ '<p class="settings-hint">'
				+ escapeHtml(
					t(
						'thematiq',
						'These are the colors and sizes Nextcloud itself is built from, such as the main text color. Changing one changes every part of Nextcloud that reads it, which is far more than the component you are looking at, and not only what this panel shows. To change one component, use its own rows instead.',
					),
				)
				+ '</p>'
				+ '<div class="nldesign-dialog-actions">'
				+ '<button class="nldesign-dialog-cancel">'
				+ escapeHtml(t('thematiq', 'Cancel'))
				+ '</button>'
				+ '<button class="nldesign-dialog-confirm nldesign-btn--primary">'
				+ escapeHtml(t('thematiq', 'Edit them anyway'))
				+ '</button>'
				+ '</div>'
				+ '</div>'
				+ '</div>'

			document.body.insertAdjacentHTML('beforeend', html)
			var overlay = document.getElementById('nldesign-base-unlock-overlay')

			function close() {
				overlay.remove()
			}

			makeDialogAccessible(overlay, close)

			overlay
				.querySelector('.nldesign-dialog-cancel')
				.addEventListener('click', close)
			overlay
				.querySelector('.nldesign-dialog-confirm')
				.addEventListener('click', function () {
					close()
					proceed()
				})
		}

		/**
		 * Build HTML for a single token row.
		 */
		function buildTokenRow(name, customVal) {
			var meta = tokenRegistry[name]
			var state = tokenEditorState[name]
			var displayVal = state
				? state.current
				: customVal !== null
					? customVal
					: ''
			var isCustom = customVal !== null && customVal !== undefined

			var badgeHtml = isCustom
				? '<span class="nldesign-token-custom-badge" title="'
					+ escapeHtml(t('thematiq', 'Custom value'))
					+ '"></span>'
				: ''

			// Accessible name for the token inputs. The visible label lives in a
			// sibling span (.nldesign-token-label), so associate it via aria-label
			// to satisfy WCAG 1.3.1/4.1.2 (axe "label").
			var inputLabel = escapeHtml(meta.label || name)
			var pickerLabel = escapeHtml(
				t('thematiq', 'Color picker for {label}', {
					label: meta.label || name,
				}),
			)

			// Locked rows render disabled rather than hidden. The value is still
			// stored and still shown, because turning the setting back off restores
			// it — hiding the control would make a kept value look like a lost one.
			// `disabled` also carries to the playground, which clones these rows.
			var locked = isTokenLocked(name)
			var lockedAttr = locked
				? ' disabled title="' + escapeHtml(lockReason(name)) + '"'
				: ''

			var inputHtml = ''
			// An `rgb` token holds a bare `r, g, b` triplet (Nextcloud 32 mixes
			// its note-card fills from one), so it gets the same picker as a
			// colour and the picker writes the triplet — see wireTokenRows().
			var format = meta.type === 'rgb' ? ' data-format="rgb"' : ''
			if (meta.type === 'duration') {
				inputHtml = buildDurationInput(name, meta, displayVal, lockedAttr)
			} else if (meta.type === 'easing') {
				inputHtml = buildEasingInput(name, meta, displayVal, lockedAttr)
			} else if (meta.type === 'color' || meta.type === 'rgb') {
				var parts =
					meta.type === 'color' && typeof TT.splitAlpha === 'function'
						? TT.splitAlpha(displayVal)
						: null
				var pickerVal =
					parts !== null ? parts.hex : normaliseColorForPicker(displayVal)
				inputHtml =
					'<div class="nldesign-color-input-wrap">'
					+ '<input type="color" class="nldesign-color-picker" aria-label="'
					+ pickerLabel
					+ '"'
					+ format
					+ ' data-token="'
					+ escapeHtml(name)
					+ '" value="'
					+ escapeHtml(pickerVal)
					+ '"'
					+ lockedAttr
					+ '>'
					+ '<input type="text" class="nldesign-color-text" aria-label="'
					+ inputLabel
					+ '"'
					+ format
					+ ' data-token="'
					+ escapeHtml(name)
					+ '" value="'
					+ escapeHtml(displayVal)
					+ '"'
					+ lockedAttr
					+ '>'
					+ (meta.type === 'color'
						? buildAlphaInput(name, meta, displayVal, parts, lockedAttr)
						: '')
					+ '</div>'
					+ (meta.type === 'color' && meta.group === 'brand'
						? buildDarkInput(name, meta, lockedAttr)
						: '')
			} else {
				inputHtml =
					'<input type="text" class="nldesign-text-input" aria-label="'
					+ inputLabel
					+ '" data-token="'
					+ escapeHtml(name)
					+ '" value="'
					+ escapeHtml(displayVal)
					+ '"'
					+ lockedAttr
					+ '>'
			}

			return (
				'<div class="nldesign-token-row'
				+ (locked ? ' nldesign-token-row--locked' : '')
				+ '" data-token-row="'
				+ escapeHtml(name)
				+ '">'
				+ '<div class="nldesign-token-label-wrap">'
				+ '<span class="nldesign-token-label">'
				+ escapeHtml(meta.label)
				+ badgeHtml
				+ '</span>'
				+ '<span class="nldesign-token-name">'
				+ escapeHtml(name)
				+ '</span>'
				+ '</div>'
				+ inputHtml
				+ '<button class="nldesign-btn nldesign-btn--small nldesign-reset-btn" data-token="'
				+ escapeHtml(name)
				+ '" title="'
				+ escapeHtml(t('thematiq', 'Reset to default'))
				+ '" aria-label="'
				+ escapeHtml(
					t('thematiq', 'Reset {label} to default', {
						label: meta.label || name,
					}),
				)
				+ '"'
				+ (locked ? ' disabled' : '')
				+ '>↺</button>'
				+ (meta.type === 'easing' ? buildMotionPreview() : '')
				+ '</div>'
			)
		}

		/**
		 * The opacity controls of a colour row: a checkerboard swatch, a range and a number field.
		 *
		 * @param {string} name The token.
		 * @param {object} meta Its registry entry.
		 * @param {string} value The shown value.
		 * @param {{hex: string, alpha: number}|null} parts The value split by splitAlpha().
		 * @param {string} lockedAttr The disabled attributes of a locked row.
		 * @return {string} HTML.
		 */
		function buildAlphaInput(name, meta, value, parts, lockedAttr) {
			var alpha = parts !== null ? parts.alpha : 100
			var label = meta.label || name
			return (
				'<span class="nldesign-color-swatch" aria-hidden="true"><span style="background:'
				+ escapeHtml(value)
				+ '"></span></span>'
				+ '<input type="range" class="nldesign-color-alpha" min="0" max="100" step="1" data-token="'
				+ escapeHtml(name)
				+ '" value="'
				+ alpha
				+ '" aria-label="'
				+ escapeHtml(t('thematiq', 'Opacity of {label}', { label: label }))
				+ '"'
				+ lockedAttr
				+ '>'
				+ '<input type="number" class="nldesign-color-alpha-number" min="0" max="100" step="1" data-token="'
				+ escapeHtml(name)
				+ '" value="'
				+ alpha
				+ '" aria-label="'
				+ escapeHtml(
					t('thematiq', 'Opacity of {label} in percent', { label: label }),
				)
				+ '"'
				+ lockedAttr
				+ '>'
			)
		}

		/**
		 * The "Dark" line of a colour row: empty means derived, shown as the placeholder.
		 *
		 * @param {string} name The token.
		 * @param {object} meta Its registry entry.
		 * @param {string} lockedAttr The disabled attributes of a locked row.
		 * @return {string} HTML.
		 */
		function buildDarkInput(name, meta, lockedAttr) {
			var placeholder =
				tokenEditorDarkDerived[name]
				|| t('thematiq', 'Derived from the light value')
			return (
				'<div class="nldesign-token-dark">'
				+ '<span class="nldesign-token-dark-label" aria-hidden="true">'
				+ escapeHtml(t('thematiq', 'Dark'))
				+ '</span>'
				+ '<input type="text" class="nldesign-dark-text" data-token="'
				+ escapeHtml(name)
				+ '" value="'
				+ escapeHtml(tokenEditorDark[name] || '')
				+ '" placeholder="'
				+ escapeHtml(placeholder)
				+ '" aria-label="'
				+ escapeHtml(
					t('thematiq', 'Dark value of {label}', {
						label: meta.label || name,
					}),
				)
				+ '"'
				+ lockedAttr
				+ '>'
				+ '</div>'
			)
		}

		/**
		 * A duration row: a number field and a unit select.
		 *
		 * @param {string} name The token.
		 * @param {object} meta Its registry entry.
		 * @param {string} value The shown value, such as `150ms`.
		 * @param {string} lockedAttr The disabled attributes of a locked row.
		 * @return {string} HTML.
		 */
		function buildDurationInput(name, meta, value, lockedAttr) {
			var match = /^\s*([0-9.]+)\s*(ms|s)\s*$/.exec(value || '')
			var amount = match !== null ? match[1] : ''
			var unit = match !== null ? match[2] : 'ms'
			var label = meta.label || name
			return (
				'<div class="nldesign-duration-input">'
				+ '<input type="number" class="nldesign-duration-number" min="0" step="any" data-token="'
				+ escapeHtml(name)
				+ '" value="'
				+ escapeHtml(amount)
				+ '" aria-label="'
				+ escapeHtml(label)
				+ '"'
				+ lockedAttr
				+ '>'
				+ '<select class="nldesign-duration-unit" data-token="'
				+ escapeHtml(name)
				+ '" aria-label="'
				+ escapeHtml(t('thematiq', 'Unit of {label}', { label: label }))
				+ '"'
				+ lockedAttr
				+ '>'
				+ ['ms', 's']
					.map(function (u) {
						return (
							'<option value="'
							+ u
							+ '"'
							+ (u === unit ? ' selected' : '')
							+ '>'
							+ u
							+ '</option>'
						)
					})
					.join('')
				+ '</select>'
				+ '</div>'
			)
		}

		/**
		 * An easing row: the five keywords and "Custom curve", which opens four number fields.
		 *
		 * @param {string} name The token.
		 * @param {object} meta Its registry entry.
		 * @param {string} value The shown value.
		 * @param {string} lockedAttr The disabled attributes of a locked row.
		 * @return {string} HTML.
		 */
		function buildEasingInput(name, meta, value, lockedAttr) {
			var curve = /^cubic-bezier\(([^)]*)\)$/.exec((value || '').trim())
			var points = curve !== null ? curve[1].split(',') : ['', '', '', '']
			var selected = curve !== null ? 'custom' : (value || 'ease').trim()
			var label = meta.label || name
			var options = [
				['linear', 'linear'],
				['ease', 'ease'],
				['ease-in', 'ease-in'],
				['ease-out', 'ease-out'],
				['ease-in-out', 'ease-in-out'],
				['custom', t('thematiq', 'Custom curve')],
			]
			return (
				'<div class="nldesign-easing-input">'
				+ '<select class="nldesign-easing-select" data-token="'
				+ escapeHtml(name)
				+ '" aria-label="'
				+ escapeHtml(label)
				+ '"'
				+ lockedAttr
				+ '>'
				+ options
					.map(function (o) {
						return (
							'<option value="'
							+ o[0]
							+ '"'
							+ (o[0] === selected ? ' selected' : '')
							+ '>'
							+ escapeHtml(o[1])
							+ '</option>'
						)
					})
					.join('')
				+ '</select>'
				+ '<span class="nldesign-easing-custom"'
				+ (selected === 'custom' ? '' : ' hidden')
				+ '>'
				+ ['x1', 'y1', 'x2', 'y2']
					.map(function (point, i) {
						return (
							'<input type="number" step="0.01" class="nldesign-easing-point" data-token="'
							+ escapeHtml(name)
							+ '" value="'
							+ escapeHtml((points[i] || '').trim())
							+ '" aria-label="'
							+ escapeHtml(
								t('thematiq', '{point} of the curve of {label}', {
									point: point,
									label: label,
								}),
							)
							+ '"'
							+ lockedAttr
							+ '>'
						)
					})
					.join('')
				+ '</span>'
				+ '</div>'
			)
		}

		/**
		 * The motion preview: a block that moves with the chosen duration and easing,
		 * and stays still, showing the values as text, under reduced motion.
		 *
		 * @return {string} HTML.
		 */
		function buildMotionPreview() {
			return (
				'<div class="nldesign-motion-preview">'
				+ '<button type="button" class="nldesign-btn nldesign-btn--small nldesign-motion-play">'
				+ escapeHtml(t('thematiq', 'Preview motion'))
				+ '</button>'
				+ '<span class="nldesign-motion-track" aria-hidden="true"><span class="nldesign-motion-block"></span></span>'
				+ '<span class="nldesign-motion-readout" role="status" aria-live="polite"></span>'
				+ '</div>'
			)
		}

		/**
		 * The value a colour picker stands for.
		 *
		 * A picker always reports #RRGGBB; on an `rgb` row the token holds the
		 * bare triplet instead, so the hex is converted before it is written.
		 *
		 * @param {HTMLInputElement} picker The colour input.
		 * @return {string} The token value.
		 */
		function pickerValue(picker) {
			if (
				picker.dataset.format === 'rgb'
				&& typeof TT.hexToRgbTriplet === 'function'
			) {
				return TT.hexToRgbTriplet(picker.value) || picker.value
			}
			return picker.value
		}

		/**
		 * Move a picker to a typed value it can show: a hex colour, or on an
		 * `rgb` row a triplet. Anything else leaves the swatch where it is.
		 *
		 * @param {HTMLInputElement} picker The colour input.
		 * @param {string} value The typed value.
		 * @return {void}
		 */
		function syncPicker(picker, value) {
			if (/^#[0-9a-fA-F]{6}$/.test(value) === true) {
				picker.value = value
				return
			}
			if (
				picker.dataset.format === 'rgb'
				&& /^\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}$/.test(value) === true
			) {
				picker.value = normaliseColorForPicker(value)
			}
		}

		/**
		 * Move a colour row's picker and opacity controls to a typed value they can show.
		 *
		 * @param {HTMLElement} container The editor.
		 * @param {string} name The token.
		 * @param {string} value The typed value.
		 * @return {void}
		 */
		function syncAlpha(container, name, value) {
			var parts =
				typeof TT.splitAlpha === 'function' ? TT.splitAlpha(value) : null
			if (parts === null) {
				return
			}
			container
				.querySelectorAll(
					'.nldesign-color-alpha[data-token="'
						+ name
						+ '"], .nldesign-color-alpha-number[data-token="'
						+ name
						+ '"]',
				)
				.forEach(function (field) {
					field.value = String(parts.alpha)
				})
			var picker = container.querySelector(
				'.nldesign-color-picker[data-token="' + name + '"]',
			)
			if (picker !== null && picker.dataset.format !== 'rgb') {
				picker.value = parts.hex
			}
		}

		/**
		 * Set a typed row's value from its controls: preview, mark unsaved.
		 *
		 * @param {HTMLElement} container The editor.
		 * @param {string} name The token.
		 * @param {string} value The value.
		 * @return {void}
		 */
		function setTypedValue(container, name, value) {
			applyLivePreview(name, value)
			markDirty(name, value, container)
		}

		/**
		 * Wire the opacity, dark, duration, easing and motion preview controls.
		 *
		 * @param {HTMLElement} container The editor.
		 * @return {void}
		 */
		function wireTypedRows(container) {
			container
				.querySelectorAll(
					'.nldesign-color-alpha, .nldesign-color-alpha-number',
				)
				.forEach(function (field) {
					field.addEventListener('input', function () {
						var name = field.dataset.token
						var picker = container.querySelector(
							'.nldesign-color-picker[data-token="' + name + '"]',
						)
						var text = container.querySelector(
							'.nldesign-color-text[data-token="' + name + '"]',
						)
						container
							.querySelectorAll(
								'.nldesign-color-alpha[data-token="'
									+ name
									+ '"], .nldesign-color-alpha-number[data-token="'
									+ name
									+ '"]',
							)
							.forEach(function (other) {
								other.value = field.value
							})
						if (picker === null || typeof TT.joinAlpha !== 'function') {
							return
						}
						var value = TT.joinAlpha(picker.value, field.value)
						if (text !== null) {
							text.value = value
						}
						setTypedValue(container, name, value)
					})
				})

			container
				.querySelectorAll('.nldesign-dark-text')
				.forEach(function (field) {
					field.addEventListener('input', function () {
						var name = field.dataset.token
						tokenEditorDark[name] = field.value.trim()
						var state = tokenEditorState[name]
						markDirty(name, state ? state.current : '', container)
					})
				})

			function durationOf(name) {
				var number = container.querySelector(
					'.nldesign-duration-number[data-token="' + name + '"]',
				)
				var unit = container.querySelector(
					'.nldesign-duration-unit[data-token="' + name + '"]',
				)
				if (number === null || unit === null || number.value.trim() === '') {
					return
				}
				setTypedValue(container, name, number.value.trim() + unit.value)
			}
			container
				.querySelectorAll(
					'.nldesign-duration-number, .nldesign-duration-unit',
				)
				.forEach(function (field) {
					field.addEventListener('input', function () {
						durationOf(field.dataset.token)
					})
					field.addEventListener('change', function () {
						durationOf(field.dataset.token)
					})
				})

			function easingOf(name) {
				var select = container.querySelector(
					'.nldesign-easing-select[data-token="' + name + '"]',
				)
				if (select === null) {
					return
				}
				var custom = select.parentNode.querySelector(
					'.nldesign-easing-custom',
				)
				if (custom !== null) {
					custom.hidden = select.value !== 'custom'
				}
				if (select.value !== 'custom') {
					setTypedValue(container, name, select.value)
					return
				}
				var points = []
				select.parentNode
					.querySelectorAll('.nldesign-easing-point')
					.forEach(function (point) {
						points.push(point.value.trim())
					})
				if (points.indexOf('') === -1) {
					setTypedValue(
						container,
						name,
						'cubic-bezier(' + points.join(', ') + ')',
					)
				}
			}
			container
				.querySelectorAll('.nldesign-easing-select, .nldesign-easing-point')
				.forEach(function (field) {
					field.addEventListener('change', function () {
						easingOf(field.dataset.token)
					})
					field.addEventListener('input', function () {
						easingOf(field.dataset.token)
					})
				})

			container
				.querySelectorAll('.nldesign-motion-play')
				.forEach(function (btn) {
					btn.addEventListener('click', function () {
						var preview = btn.parentNode
						var block = preview.querySelector('.nldesign-motion-block')
						var readout = preview.querySelector(
							'.nldesign-motion-readout',
						)
						var quick = tokenEditorState['--animation-quick']
						var easing = tokenEditorState['--nldesign-animation-easing']
						var duration = (quick && quick.current) || '100ms'
						var curve = (easing && easing.current) || 'ease'
						var reduced =
							typeof window.matchMedia === 'function'
							&& window.matchMedia('(prefers-reduced-motion: reduce)')
								.matches
						readout.textContent = t(
							'thematiq',
							'Duration {duration}, easing {easing}.',
							{
								duration: duration,
								easing: curve,
							},
						)
						if (reduced) {
							return
						}
						block.style.transition =
							'transform ' + duration + ' ' + curve
						block.classList.toggle('nldesign-motion-block--moved')
					})
				})
		}

		/**
		 * The dark values to send: each own dark value, with its colour's light value in
		 * `overrides` too, because the server keeps a dark value only next to its light one.
		 *
		 * @param {Object<string,string>} overrides The light values being written; completed in place.
		 * @return {Object<string,string>} Token => dark value.
		 */
		function collectDarkOverrides(overrides) {
			var dark = {}
			Object.keys(tokenEditorDark).forEach(function (name) {
				var value = (tokenEditorDark[name] || '').trim()
				var state = tokenEditorState[name]
				if (value === '' || state === undefined) {
					return
				}
				if (overrides[name] === undefined && state.current.trim() !== '') {
					overrides[name] = state.current.trim()
				}
				dark[name] = value
			})
			return dark
		}

		/**
		 * Wire event listeners on all token rows inside a container.
		 */
		function wireTokenRows(container) {
			container
				.querySelectorAll('.nldesign-color-picker')
				.forEach(function (picker) {
					picker.addEventListener('input', function () {
						var name = picker.dataset.token
						var value = pickerValue(picker)
						var alpha = container.querySelector(
							'.nldesign-color-alpha[data-token="' + name + '"]',
						)
						if (alpha !== null && typeof TT.joinAlpha === 'function') {
							value = TT.joinAlpha(picker.value, alpha.value)
						}
						var textField = container.querySelector(
							'.nldesign-color-text[data-token="' + name + '"]',
						)
						if (textField !== null) {
							textField.value = value
						}
						applyLivePreview(name, value)
						markDirty(name, value, container)
					})
				})

			container
				.querySelectorAll('.nldesign-color-text')
				.forEach(function (field) {
					field.addEventListener('input', function () {
						var name = field.dataset.token
						var value = field.value.trim()
						var picker = container.querySelector(
							'.nldesign-color-picker[data-token="' + name + '"]',
						)
						if (picker !== null) {
							syncPicker(picker, value)
						}
						syncAlpha(container, name, value)
						applyLivePreview(name, value)
						markDirty(name, value, container)
					})
				})

			wireTypedRows(container)

			container
				.querySelectorAll('.nldesign-text-input')
				.forEach(function (field) {
					field.addEventListener('input', function () {
						var name = field.dataset.token
						var value = field.value.trim()
						applyLivePreview(name, value)
						markDirty(name, value, container)
					})
				})

			container
				.querySelectorAll('.nldesign-reset-btn')
				.forEach(function (btn) {
					btn.addEventListener('click', function () {
						var name = btn.dataset.token
						var state = tokenEditorState[name]
						if (state === undefined) {
							return
						}
						// Back to the saved value, so a reset undoes the edit
						// rather than a save the admin already made.
						var defaultVal =
							state.saved !== undefined ? state.saved : state.resolved
						var textField = container.querySelector(
							'.nldesign-color-text[data-token="'
								+ name
								+ '"], .nldesign-text-input[data-token="'
								+ name
								+ '"]',
						)
						var picker = container.querySelector(
							'.nldesign-color-picker[data-token="' + name + '"]',
						)
						if (textField !== null) {
							textField.value = defaultVal
						}
						if (picker !== null) {
							picker.value = normaliseColorForPicker(defaultVal)
						}
						var row = container.querySelector(
							'[data-token-row="' + name + '"]',
						)
						if (row !== null) {
							var badge = row.querySelector(
								'.nldesign-token-custom-badge',
							)
							if (badge !== null) {
								badge.remove()
							}
						}
						// A saved value stays on the preview; with none, the
						// preview falls back to what the theme itself declares.
						writeLiveToken(
							name,
							typeof state.custom === 'string' ? state.custom : '',
						)
						schedulePreviewRepaint()
						tokenEditorState[name].current = defaultVal
						tokenEditorState[name].isDirty = false
						updateSaveStatus()
					})
				})
		}

		// Pending preview repaint, so a drag cannot queue more than one a frame.
		var previewFrame = null

		/**
		 * Ask for a preview repaint at the next frame, at most one per frame.
		 *
		 * A colour input fires `input` continuously while the pointer is down —
		 * well above 60 a second on a fast mouse — and each repaint reads the
		 * computed cascade. Repainting per event did work the frame could never
		 * show, because several events land between two paints and only the
		 * last one is visible. Coalescing keeps the preview exactly as current
		 * while doing a fraction of the work.
		 *
		 * @return {void}
		 */
		function schedulePreviewRepaint() {
			if (previewFrame !== null) {
				return
			}
			previewFrame = requestAnimationFrame(function () {
				previewFrame = null
				updatePreview(tokenSetSelect ? tokenSetSelect.value : '')
			})
		}

		/**
		 * Where an unsaved edit is written: the preview, never the page.
		 *
		 * An edit is a preview until it is saved — as overrides on the active
		 * theme, or as a theme of its own that is then selected. Written on
		 * <html>, every edit repainted the real Nextcloud the admin was working
		 * in, so the page wore a theme nobody had saved. On the preview it
		 * reaches exactly the specimens drawn inside it, which read the same
		 * tokens the real components do (css/component-scopes.css captures the
		 * Nextcloud values on the preview as well as on body).
		 *
		 * @return {Element} The preview root, or <html> on a page without one.
		 */
		function previewTarget() {
			return previewRoot !== null ? previewRoot : document.documentElement
		}

		/**
		 * Whether a token belongs to the family Nextcloud derives from the
		 * primary colour (`--color-primary`, `-element`, `-light`, `-hover`…).
		 *
		 * @param {string} name The custom property.
		 * @return {boolean} True for a primary-family token.
		 */
		function isPrimaryFamily(name) {
			return name.indexOf('--color-primary') === 0
		}

		/**
		 * Write an unsaved value, or clear it, where an edit is shown.
		 *
		 * The editor's own tabs, chips, buttons and badges are drawn in the
		 * brand primary, so an edit to that family is written on
		 * #nldesign-settings as well: on the preview alone, the controls around
		 * it kept the saved colour while everything inside showed the new one.
		 * The settings section is as far as it reaches — the rest of the page
		 * still wears only what has been saved.
		 *
		 * @param {string} name The custom property.
		 * @param {string} value The value, or an empty string to clear it.
		 * @return {void}
		 */
		function writeLiveToken(name, value) {
			var targets = [previewTarget()]
			if (settingsEl !== null && isPrimaryFamily(name) === true) {
				targets.push(settingsEl)
			}
			targets.forEach(function (node) {
				if (value.trim() === '') {
					node.style.removeProperty(name)
				} else {
					node.style.setProperty(name, value, 'important')
				}
			})
		}

		/**
		 * Drop every unsaved value written where an edit is shown.
		 *
		 * Called when the editor is rebuilt from the server: after an apply and
		 * after an import. The rebuilt rows hold only what is saved, so a value
		 * an edit wrote inline on the preview (or, for the primary family, on
		 * the settings section) is now held by nothing, and being the nearer
		 * declaration it would keep beating the theme for the rest of the page
		 * session. The playground writes the same names on the preview from its
		 * cloned rows, so this clears those too. The preview's own `--prev-*`
		 * scale is not an edit and is left alone.
		 *
		 * @param {Array<string>} names The token names the editor can write.
		 * @return {void}
		 */
		function clearLiveTokens(names) {
			var targets = [previewTarget()]
			if (settingsEl !== null) {
				targets.push(settingsEl)
			}
			targets.forEach(function (node) {
				names.forEach(function (name) {
					node.style.removeProperty(name)
				})
			})
			// The `--prev-*` scale was read off the cascade the edits were in.
			schedulePreviewRepaint()
		}

		function applyLivePreview(name, value) {
			// The custom property is written immediately, NOT deferred: this is
			// what repaints the specimens in the preview, and the browser
			// already batches it into the next frame for free. Only the rich
			// preview — which has to READ the cascade back — is worth delaying.
			//
			// WRITTEN `!important`, and it has to be. CustomOverridesService
			// emits every stored token as `!important` (see the note in its
			// write()), so once a token had been saved ONCE, this inline
			// declaration lost the cascade to custom-overrides.css and dragging
			// its picker moved nothing on the page. An admin who had already
			// saved a colour could never preview a different one — which reads
			// as the editor being dead, not as a cascade nicety.
			//
			// Inline `!important` outranks any author stylesheet, so the preview
			// now wins over the stored value it is about to replace.
			writeLiveToken(name, value)
			schedulePreviewRepaint()
		}

		function markDirty(name, value, container) {
			if (tokenEditorState[name] === undefined) {
				tokenEditorState[name] = {
					resolved: '',
					custom: null,
					current: value,
					isDirty: true,
				}
			}
			tokenEditorState[name].current = value
			tokenEditorState[name].isDirty = true

			var row = container.querySelector('[data-token-row="' + name + '"]')
			var label =
				row !== null ? row.querySelector('.nldesign-token-label') : null
			if (
				label !== null
				&& label.querySelector('.nldesign-token-custom-badge') === null
			) {
				label.insertAdjacentHTML(
					'beforeend',
					'<span class="nldesign-token-custom-badge"></span>',
				)
			}
			updateSaveStatus()
		}

		function updateSaveStatus() {
			var statusEl = document.getElementById('nldesign-save-status')
			if (statusEl === null) {
				return
			}
			var dirtyCount = Object.keys(tokenEditorState).filter(function (k) {
				return tokenEditorState[k].isDirty === true
			}).length
			statusEl.textContent =
				dirtyCount > 0 ? t('thematiq', 'Unsaved changes') : ''
		}

		/* ==========================================================================
		 * SAVE CONFIRMATION
		 *
		 * Two dialogs, because the two situations ask different questions.
		 *
		 * On the stock `nextcloud` set there is a real fork: the edits can be
		 * kept as a NEW token set, or written straight over the running
		 * Nextcloud theme. That choice has to be made before anything is
		 * written, because the two go to different places.
		 *
		 * On any other set there is nothing to choose — the overrides belong to
		 * the set that is on — so it is a plain confirmation.
		 *
		 * Both carry "do not ask again", and both flags are turned back on from
		 * the pair of controls under the editor. A one-way switch buried in a
		 * dialog is how an admin loses the only offer of the new-set path.
		 * ========================================================================== */

		// The stock set's id, as CssInjectionService::STOCK_TOKEN_SET spells it.
		// It is the one set whose "save" has a second route, because it is the
		// running Nextcloud theme rather than a theme this app ships.
		var STOCK_TOKEN_SET = 'nextcloud'

		// Server-rendered, and kept in step as the dialogs and the controls
		// under the editor change them.
		var confirmSaveStock = loadInitialState('confirmSaveStock', true) === true
		var confirmSaveTheme = loadInitialState('confirmSaveTheme', true) === true

		/**
		 * Put the two controls under the editor in step with the flags.
		 *
		 * Called after a dialog changes one, so ticking "do not ask again" and
		 * then looking down at the controls shows the same fact in both places.
		 *
		 * @return {void}
		 */
		function refreshConfirmControls() {
			var stockEl = document.getElementById('nldesign-confirm-save-stock')
			var themeEl = document.getElementById('nldesign-confirm-save-theme')
			// Ticked is "do not ask", the inverse of the stored flag.
			if (stockEl !== null) {
				stockEl.checked = confirmSaveStock === false
			}
			if (themeEl !== null) {
				themeEl.checked = confirmSaveTheme === false
			}
		}

		/**
		 * Wire the two controls under the editor, and set their initial state.
		 *
		 * @return {void}
		 */
		function wireConfirmControls() {
			var stockEl = document.getElementById('nldesign-confirm-save-stock')
			var themeEl = document.getElementById('nldesign-confirm-save-theme')
			if (stockEl === null || themeEl === null) {
				return
			}

			refreshConfirmControls()

			stockEl.addEventListener('change', function () {
				confirmSaveStock = stockEl.checked === false
				saveConfirmFlags()
			})
			themeEl.addEventListener('change', function () {
				confirmSaveTheme = themeEl.checked === false
				saveConfirmFlags()
			})
		}

		/**
		 * Persist both confirmation flags.
		 *
		 * Sent as a pair rather than one at a time: the controls under the
		 * editor render both, and a single round trip cannot leave the two
		 * halves of the preference disagreeing if one request fails.
		 *
		 * @return {Promise} Resolves when the server has stored them.
		 */
		function saveConfirmFlags() {
			return fetch(
				OC.generateUrl('/apps/thematiq/settings/save-confirmations'),
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify({
						confirmSaveStock: confirmSaveStock,
						confirmSaveTheme: confirmSaveTheme,
					}),
				},
			).catch(function (err) {
				console.error('Error saving the confirmation settings:', err)
			})
		}

		/**
		 * Whether the active set is the stock one, read fresh.
		 *
		 * The dropdown can move under the editor without a reload, so the
		 * server-rendered flag is only the starting point.
		 *
		 * @return {boolean} True when the stock Nextcloud set is active.
		 */
		function onStockSet() {
			if (tokenSetSelect !== null && tokenSetSelect.value !== '') {
				return tokenSetSelect.value === STOCK_TOKEN_SET
			}

			return pageTokenSetId === STOCK_TOKEN_SET
		}

		/**
		 * Put the "do not ask again" row into a dialog and report its state.
		 *
		 * @param {Element} overlay The dialog overlay.
		 * @param {string} which Which flag this dialog controls.
		 *
		 * @return {void}
		 */
		function wireDoNotAskAgain(overlay, which) {
			var box = overlay.querySelector('.nldesign-dialog-dontask')
			if (box === null) {
				return
			}

			box.addEventListener('change', function () {
				if (which === 'stock') {
					confirmSaveStock = box.checked === false
				} else {
					confirmSaveTheme = box.checked === false
				}
				saveConfirmFlags().then(refreshConfirmControls)
			})
		}

		/**
		 * The markup both dialogs share for the "do not ask again" row.
		 *
		 * @return {string} The row's HTML.
		 */
		function dontAskAgainRow() {
			return (
				'<label class="nldesign-dialog-dontask-row">'
				+ '<input type="checkbox" class="nldesign-dialog-dontask">'
				+ escapeHtml(t('thematiq', 'Do not ask again'))
				+ '</label>'
			)
		}

		/**
		 * Ask before saving, and run `proceed` with the chosen route.
		 *
		 * `proceed` is called with `'new-set'` to keep the edits as a token set
		 * of their own, or `'overrides'` to write them over the active theme.
		 * Cancelling calls nothing at all — deliberately, so a dismissed dialog
		 * can never be mistaken for a save.
		 *
		 * @param {Function} proceed Called with the chosen route.
		 *
		 * @return {void}
		 */
		function confirmSave(proceed) {
			var stock = onStockSet()

			if (stock === false && confirmSaveTheme === false) {
				proceed('overrides')
				return
			}
			if (stock === true && confirmSaveStock === false) {
				proceed('overrides')
				return
			}

			if (stock === true) {
				openStockSaveDialog(proceed)
				return
			}

			openThemeSaveDialog(proceed)
		}

		/**
		 * The stock-set dialog: new token set, or over the Nextcloud theme.
		 *
		 * @param {Function} proceed Called with the chosen route.
		 *
		 * @return {void}
		 */
		function openStockSaveDialog(proceed) {
			var html =
				'<div class="nldesign-dialog-overlay" id="nldesign-save-dialog-overlay">'
				+ '<div class="nldesign-dialog">'
				+ '<h3>'
				+ escapeHtml(t('thematiq', 'Keep these changes as a new theme?'))
				+ '</h3>'
				+ '<p class="settings-hint">'
				+ escapeHtml(
					t(
						'thematiq',
						'You are working on the stock Nextcloud theme. Save the changes as a token set of their own, or write them over the Nextcloud theme itself. Writing over it changes Nextcloud for everyone; "Reset theme to Nextcloud" brings the original back.',
					),
				)
				+ '</p>'
				+ '<label class="nldesign-dialog-field">'
				+ escapeHtml(t('thematiq', 'Name for the new theme'))
				+ '<input type="text" id="nldesign-save-newset-name" '
				+ 'placeholder="'
				+ escapeHtml(t('thematiq', 'My organisation'))
				+ '">'
				+ '</label>'
				// Where the server's refusal lands. In the dialog, beside the
				// field that caused it, because the one thing an admin needs
				// after "that name is taken" is the name still being there to
				// edit. A toast over a closed dialog loses the whole export.
				+ '<p class="nldesign-dialog-error" id="nldesign-save-newset-error"'
				+ ' role="alert" hidden></p>'
				+ dontAskAgainRow()
				+ '<div class="nldesign-dialog-actions">'
				+ '<button class="nldesign-dialog-cancel">'
				+ escapeHtml(t('thematiq', 'Cancel'))
				+ '</button>'
				+ '<button class="nldesign-dialog-overwrite">'
				+ escapeHtml(t('thematiq', 'No, change the Nextcloud theme'))
				+ '</button>'
				+ '<button class="nldesign-dialog-confirm nldesign-btn--primary">'
				+ escapeHtml(t('thematiq', 'Yes, save as a new theme'))
				+ '</button>'
				+ '</div>'
				+ '</div>'
				+ '</div>'

			document.body.insertAdjacentHTML('beforeend', html)
			var overlay = document.getElementById('nldesign-save-dialog-overlay')

			function close() {
				overlay.remove()
			}

			makeDialogAccessible(overlay, close)
			wireDoNotAskAgain(overlay, 'stock')

			overlay
				.querySelector('.nldesign-dialog-cancel')
				.addEventListener('click', close)
			overlay
				.querySelector('.nldesign-dialog-overwrite')
				.addEventListener('click', function () {
					close()
					proceed('overrides')
				})
			overlay
				.querySelector('.nldesign-dialog-confirm')
				.addEventListener('click', function () {
					var nameEl = document.getElementById('nldesign-save-newset-name')
					if (nameEl === null) {
						return
					}

					var name = nameEl.value.trim()
					if (name === '') {
						// Refused here rather than at the server, because the
						// name is the one thing this dialog exists to collect,
						// and a round trip to be told so loses what was typed.
						showError(t('thematiq', 'Give the new theme a name.'))
						return
					}

					// The dialog STAYS OPEN across the request and closes only
					// once the set exists. A name collision is the expected
					// failure here — the server answers 409 with the name it
					// already has — and the admin's next move is to change one
					// word and try again, which is only possible if the dialog
					// and everything typed into it are still on screen.
					busy(true)
					showError('')
					proceed('new-set', name, {
						done: function () {
							busy(false)
							close()
						},
						fail: function (message) {
							busy(false)
							showError(message)
						},
					})
				})

			/**
			 * Show, or clear, the server's refusal inside the dialog.
			 *
			 * @param {string} message The message, or '' to clear it.
			 *
			 * @return {void}
			 */
			function showError(message) {
				var errorEl = document.getElementById('nldesign-save-newset-error')
				var nameEl = document.getElementById('nldesign-save-newset-name')
				if (errorEl === null) {
					return
				}

				errorEl.textContent = message
				errorEl.hidden = message === ''
				if (nameEl === null) {
					return
				}

				if (message === '') {
					nameEl.removeAttribute('aria-invalid')
					return
				}

				nameEl.setAttribute('aria-invalid', 'true')
				nameEl.focus()
				nameEl.select()
			}

			/**
			 * Lock the dialog's controls while the set is being created.
			 *
			 * @param {boolean} on Whether a request is in flight.
			 *
			 * @return {void}
			 */
			function busy(on) {
				overlay
					.querySelectorAll('button, input')
					.forEach(function (control) {
						control.disabled = on
					})
			}
		}

		/**
		 * The plain confirmation shown on every set that is not stock.
		 *
		 * @param {Function} proceed Called with the chosen route.
		 *
		 * @return {void}
		 */
		function openThemeSaveDialog(proceed) {
			var setName =
				tokenSetSelect !== null
				&& tokenSetsData[tokenSetSelect.value] !== undefined
					? tokenSetsData[tokenSetSelect.value].name
					: tokenSetSelect !== null
						? tokenSetSelect.value
						: ''

			var html =
				'<div class="nldesign-dialog-overlay" id="nldesign-save-dialog-overlay">'
				+ '<div class="nldesign-dialog nldesign-dialog--small">'
				+ '<h3>'
				+ escapeHtml(t('thematiq', 'Save these overrides?'))
				+ '</h3>'
				+ '<p class="settings-hint">'
				+ escapeHtml(
					t('thematiq', 'They are applied on top of {set}.', {
						set: setName,
					}),
				)
				+ '</p>'
				+ dontAskAgainRow()
				+ '<div class="nldesign-dialog-actions">'
				+ '<button class="nldesign-dialog-cancel">'
				+ escapeHtml(t('thematiq', 'Cancel'))
				+ '</button>'
				+ '<button class="nldesign-dialog-confirm nldesign-btn--primary">'
				+ escapeHtml(t('thematiq', 'Save overrides'))
				+ '</button>'
				+ '</div>'
				+ '</div>'
				+ '</div>'

			document.body.insertAdjacentHTML('beforeend', html)
			var overlay = document.getElementById('nldesign-save-dialog-overlay')

			function close() {
				overlay.remove()
			}

			makeDialogAccessible(overlay, close)
			wireDoNotAskAgain(overlay, 'theme')

			overlay
				.querySelector('.nldesign-dialog-cancel')
				.addEventListener('click', close)
			overlay
				.querySelector('.nldesign-dialog-confirm')
				.addEventListener('click', function () {
					close()
					proceed('overrides')
				})
		}

		/**
		 * The design system the page is currently wearing.
		 *
		 * Read off the selected option's `data-design-system`, which is the same
		 * attribute the badge reads, so the value saved into a new theme is the
		 * one the panel is showing the admin.
		 *
		 * @return {string} A design-system id, defaulting to nldesign.
		 */
		function currentDesignSystemId() {
			if (tokenSetSelect === null) {
				return 'nldesign'
			}

			var option = tokenSetSelect.querySelector(
				'option[value="' + tokenSetSelect.value + '"]',
			)

			return option === null
				? 'nldesign'
				: option.getAttribute('data-design-system') || 'nldesign'
		}

		/**
		 * Serialise what the page is wearing into a new custom token set.
		 *
		 * Reuses the playground's exporter — the same function behind "Export as
		 * token set" — so a set created here and one downloaded there are the
		 * same file. It is posted to the custom-set upload endpoint rather than
		 * downloaded, which is what makes this one click instead of a round trip
		 * through the admin's file manager.
		 *
		 * @param {string} name The display name the admin typed.
		 *
		 * @return {void}
		 */
		function saveAsNewTokenSet(name, report) {
			var playground = window.ThematiqPlayground
			if (playground === undefined || playground.exportCss === undefined) {
				report.fail(
					t(
						'thematiq',
						'The theme exporter is not loaded on this page, so no new theme was created.',
					),
				)
				return
			}

			// `playgroundExportTokens`, NOT `playgroundTokens`.
			//
			// The second is what the instrument DRAWS with: the active set on top
			// of css/systems/nldesign/defaults.css, so every token has a value to
			// show. Exporting from it wrote all 200 of them — 115 component
			// tokens of Rijkshuisstijl defaults — into a theme whose author had
			// changed one colour. The first is what the set DECLARES, which for
			// the stock set is resolved from the running instance rather than
			// from a file. See PlaygroundStateService::getExportTokens().
			// Captured before the request, so the set is recorded against the
			// design system the page was wearing when it was serialised, not
			// whatever the dropdown says by the time the response lands.
			var designSystem = currentDesignSystemId()

			var result = playground.exportCss(
				playground.liveTokens(
					loadInitialState('playgroundExportTokens', {}),
					function (n, fallback) {
						return readVar(n, fallback)
					},
				),
				collectSavedAndDirtyOverrides(),
				loadInitialState('playgroundTokenSources', {}),
				// Marked in the file too, so the stored theme, downloaded from
				// the custom set list and uploaded again, comes back the same.
				designSystem,
			)

			// An override the exporter could not express is a value the admin
			// set, saw applied, and would not find in the theme they just saved.
			// The download path has always said so; this one used to drop them
			// without a word, which is how a header colour went missing between
			// the editor and the file.
			if (result.unexpressed.length > 0) {
				notify(
					t(
						'thematiq',
						'These values could not be written into a theme and are kept as overrides instead: {names}',
						{ names: result.unexpressed.join(', ') },
					),
				)
			}

			fetch(OC.generateUrl('/apps/thematiq/settings/tokensets/upload'), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				// `sourceName` is what the custom-set list shows as the origin.
				// Saying it came from the editor rather than leaving it blank is
				// what tells a later admin why a set exists that nobody uploaded.
				//
				// `raw` says this is already a token set, so the server stores it
				// as it arrived instead of running it through the NLDS converter,
				// whose job is to complete an unknown document and which therefore
				// fills every token the file leaves out.
				//
				// `designSystem` is the one the page is wearing, so a theme saved
				// off stock Nextcloud stays stock Nextcloud instead of coming back
				// with the whole nldesign layer on top of it. Allow-listed server
				// side; this is a claim, not a decision.
				//
				// `captureTheming` copies Nextcloud's own branding into the new
				// theme as well, so it comes back whenever the theme is applied.
				body: JSON.stringify({
					name: name,
					content: result.css,
					sourceName: t('thematiq', 'Token editor'),
					raw: true,
					designSystem: designSystem,
					captureTheming: true,
				}),
			})
				.then(function (r) {
					// Read the body whatever the status: the refusals worth
					// showing an admin — 409 for a name already taken, 422 for a
					// name with nothing to slugify — all carry their reason in
					// `error`, and throwing on `!r.ok` would replace every one of
					// them with "could not be created".
					return r.json().then(
						function (data) {
							return { ok: r.ok, status: r.status, data: data }
						},
						function () {
							return { ok: r.ok, status: r.status, data: {} }
						},
					)
				})
				.then(function (result) {
					if (result.data.error) {
						report.fail(result.data.error)
						return
					}
					if (result.ok === false) {
						report.fail(
							t(
								'thematiq',
								'The new theme could not be created (HTTP {status}).',
								{ status: String(result.status) },
							),
						)
						return
					}

					report.done()
					applyCreatedTokenSet(result.data.id, name, designSystem)
					rememberCapturedTheming(result.data.id, result.data.theming)
				})
				.catch(function (err) {
					console.error('Error creating the token set:', err)
					report.fail(t('thematiq', 'The new theme could not be created.'))
				})
		}

		/**
		 * Put a just-created set in the dropdown, then make it the live theme.
		 *
		 * Saving a theme IS choosing it. The admin has just described the thing
		 * they want the instance to look like and named it; leaving the instance
		 * on the theme they were editing away from, with a notice telling them to
		 * go and pick it, made a successful save look like a failed one.
		 *
		 * The set is brand new, so the dropdown has no option for it — the server
		 * rendered that list before it existed. It is inserted here rather than by
		 * re-rendering the panel, because a re-render would drop every unsaved row
		 * in the editor.
		 *
		 * No apply dialog either. That dialog exists to show what CHANGES when you
		 * move between two themes; here the page is already wearing these values
		 * (the editor has been previewing them inline all along), so the only work
		 * left is to make the server agree and swap the stylesheets under it.
		 *
		 * @param {string} id   The new set's id, as the server assigned it.
		 * @param {string} name The display name the admin typed.
		 *
		 * @return {void}
		 */
		function applyCreatedTokenSet(id, name, designSystem) {
			if (typeof id !== 'string' || id === '') {
				// Created, but we cannot name it — say so rather than silently
				// leaving the instance on the old theme.
				notify(
					t(
						'thematiq',
						'Theme "{name}" was created but could not be applied. Pick it in the theme dropdown.',
						{ name: name },
					),
				)
				return
			}

			// The set was saved carrying the design system the page was wearing,
			// so the dropdown entry and the badge say the same thing the server
			// stored — a theme made from stock Nextcloud reads as stock, not as
			// NL Design System.
			tokenSetsData[id] = { id: id, name: name, design_system: designSystem }

			if (
				tokenSetSelect !== null
				&& tokenSetSelect.querySelector('option[value="' + id + '"]')
					=== null
			) {
				var option = document.createElement('option')
				option.value = id
				option.textContent = name
				option.dataset.designSystem = designSystem
				tokenSetSelect.appendChild(option)
			}

			commitTokenSetChange(id, false)
				.then(function (data) {
					if (data.status !== 'ok') {
						throw new Error(data.error || 'Token set change failed')
					}

					// reflectSelection() moves the dropdown WITHOUT opening the
					// apply dialog, which its own change handler would.
					reflectSelection(id)

					return applyLayersFor(id)
				})
				.then(function (swapped) {
					notify(
						swapped === true
							? t('thematiq', 'Theme "{name}" created and applied.', {
									name: name,
								})
							: t(
									'thematiq',
									'Theme "{name}" created and set as the active theme. Reload the page to see it.',
									{ name: name },
								),
					)
				})
				.catch(function (err) {
					console.error('Error applying the new token set:', err)
					notify(
						t(
							'thematiq',
							'Theme "{name}" was created but could not be applied. Pick it in the theme dropdown.',
							{ name: name },
						),
					)
				})
		}

		/**
		 * The dirty tokens, in the shape the overrides endpoint stores.
		 *
		 * @return {Object} Token name → value.
		 */
		function collectOverrides() {
			var overrides = {}
			Object.keys(tokenEditorState).forEach(function (name) {
				var state = tokenEditorState[name]
				var value = state.current.trim()
				if (value !== '' && value !== state.resolved) {
					overrides[name] = value
				}
			})

			return overrides
		}

		/**
		 * The saved overrides with the unsaved edits on top.
		 *
		 * collectOverrides() is what the editor has CHANGED, so a value saved
		 * earlier — which reads back as the live value — is not in it. A new
		 * theme made from here has to carry both, or the edits an admin saved
		 * over the Nextcloud theme would be missing from the theme made of it.
		 *
		 * @return {Object} Token name → value.
		 */
		function collectSavedAndDirtyOverrides() {
			var overrides = {}
			Object.keys(tokenEditorState).forEach(function (name) {
				var custom = tokenEditorState[name].custom
				if (typeof custom === 'string' && custom.trim() !== '') {
					overrides[name] = custom.trim()
				}
			})

			return Object.assign(overrides, collectOverrides())
		}

		/**
		 * The whole overrides file as it should be after this save.
		 *
		 * The endpoint REPLACES the file, so what is sent has to be every value
		 * that stays saved, not only what changed. collectOverrides() alone was
		 * not that: a value saved earlier is on the page after a reload, reads
		 * back as `resolved`, and so never differed from it — every save after a
		 * reload therefore deleted every earlier one, and each component whose
		 * own colour had been saved fell back to the primary colour.
		 *
		 * An edited row sends its value, and clearing its field is how a value
		 * stops being saved. A row nobody touched keeps what is stored.
		 *
		 * @return {Object} Token name → value.
		 */
		function collectOverridesToWrite() {
			var overrides = {}
			Object.keys(tokenEditorState).forEach(function (name) {
				var state = tokenEditorState[name]
				var saved =
					typeof state.custom === 'string' && state.custom.trim() !== ''
						? state.custom.trim()
						: null
				if (state.isDirty !== true) {
					if (saved !== null) {
						overrides[name] = saved
					}
					return
				}
				var value = state.current.trim()
				if (value !== '' && (saved !== null || value !== state.resolved)) {
					overrides[name] = value
				}
			})

			return overrides
		}

		/**
		 * Keep a theme's freshly captured branding in the page's copy of the
		 * set list, so the apply dialog compares against what was just saved
		 * rather than what the page was rendered with.
		 *
		 * @param {string} id The token set id.
		 * @param {Object|undefined} theming The captured block the server returned.
		 * @return {void}
		 */
		function rememberCapturedTheming(id, theming) {
			if (!id || !theming || typeof theming !== 'object') {
				return
			}
			if (tokenSetsData[id] === undefined) {
				tokenSetsData[id] = { id: id }
			}
			tokenSetsData[id].theming = theming
		}

		function saveOverrides() {
			confirmSave(function (route, name, report) {
				if (route === 'new-set') {
					saveAsNewTokenSet(name, report)
					return
				}
				writeOverrides()
			})
		}

		/**
		 * The refusal of a save in the administrator's language: each token by its label,
		 * with why its value does not fit. The server names the reason in English.
		 *
		 * @param {Object<string,string>} rejected Token => the server's reason.
		 * @return {string} The message.
		 */
		function rejectedMessage(rejected) {
			var reasons = {
				'not an editable token': t(
					'thematiq',
					'the editor cannot set this token',
				),
				'not an allowed value': t('thematiq', 'this value is not allowed'),
				'not a valid color value': t('thematiq', 'this is not a colour'),
				'not a valid rgb value': t('thematiq', 'this is not a colour'),
				'not a valid duration value': t(
					'thematiq',
					'use a number with ms or s, up to 5 seconds',
				),
				'not a valid easing value': t(
					'thematiq',
					'use an easing keyword or a curve with both x values from 0 to 1',
				),
				'no dark value for this token': t(
					'thematiq',
					'this token has no dark value',
				),
			}
			var parts = Object.keys(rejected).map(function (name) {
				var reason = String(rejected[name])
				var dark = reason.indexOf('dark value: ') === 0
				var key = dark ? reason.slice('dark value: '.length) : reason
				var text = reasons[key] || reason
				var meta = tokenRegistry[name] || {}
				var label = meta.label ? meta.label + ' (' + name + ')' : name
				return dark
					? t('thematiq', '{label}, dark value: {reason}', {
							label: label,
							reason: text,
						})
					: t('thematiq', '{label}: {reason}', {
							label: label,
							reason: text,
						})
			})
			return t('thematiq', 'Nothing was saved. {problems}.', {
				problems: parts.join('; '),
			})
		}

		/**
		 * The POST body of a save; `darkOverrides` only when there are any.
		 *
		 * @param {Object<string,string>} overrides The light values.
		 * @return {object} The body.
		 */
		function overridesPayload(overrides) {
			var dark = collectDarkOverrides(overrides)
			var body = {
				overrides: overrides,
				tokenSet: editedTokenSetId(),
				captureTheming: true,
			}
			if (Object.keys(dark).length > 0) {
				body.darkOverrides = dark
			}
			return body
		}

		function writeOverrides() {
			var overrides = collectOverridesToWrite()

			var btn = document.getElementById('nldesign-save-btn')
			if (btn !== null) {
				btn.disabled = true
			}

			fetch(OC.generateUrl('/apps/thematiq/settings/overrides'), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				// `captureTheming`: the theme also keeps Nextcloud's own branding
				// as it is now (colours, background, logos, favicon), so applying
				// it later puts that back. The server skips the stock set.
				body: JSON.stringify(overridesPayload(overrides)),
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					if (btn !== null) {
						btn.disabled = false
					}
					if (data.status === 'ok') {
						rememberCapturedTheming(editedTokenSetId(), data.theming)
						// What was just written is now the saved value: the
						// reset button returns to it, and no row is unsaved.
						Object.keys(tokenEditorState).forEach(function (k) {
							var state = tokenEditorState[k]
							state.custom =
								overrides[k] !== undefined ? overrides[k] : null
							state.saved = state.current
							state.isDirty = false
						})
						var editor = document.getElementById('nldesign-token-editor')
						if (editor !== null) {
							editor
								.querySelectorAll('.nldesign-token-custom-badge')
								.forEach(function (badge) {
									badge.remove()
								})
						}
						// Saved, so applied: the page takes the values from the
						// file it loads rather than from the preview.
						refreshCustomOverridesLink()
						updateSaveStatus()
						notify(t('thematiq', 'Token overrides saved.'))
					} else if (data.rejected && typeof data.rejected === 'object') {
						notify(rejectedMessage(data.rejected))
					} else {
						notify(
							t('thematiq', 'Failed to save overrides:')
								+ (data.error || ''),
						)
					}
				})
				.catch(function (err) {
					if (btn !== null) {
						btn.disabled = false
					}
					console.error('Error saving overrides:', err)
					notify(t('thematiq', 'Failed to save overrides.'))
				})
		}

		/**
		 * Download a file from a CSRF-protected endpoint.
		 *
		 * Fetched with the request token rather than followed as a link: a
		 * plain link or `window.location` carries no token, so Nextcloud
		 * refused it with 412 and the browser reported the download as failed
		 * ("site not available").
		 *
		 * @param {string} url          The endpoint.
		 * @param {string} fallbackName The file name when the response names none.
		 * @param {string} failure      What to tell the admin when it fails.
		 *
		 * @return {void}
		 */
		function downloadWithToken(url, fallbackName, failure) {
			var name = fallbackName
			fetch(url, {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					if (r.ok === false) {
						throw new Error('download failed: ' + r.status)
					}
					var disposition = r.headers
						? r.headers.get('Content-Disposition') || ''
						: ''
					var match = /filename="?([^";]+)"?/.exec(disposition)
					if (match !== null) {
						name = match[1]
					}
					return r.blob()
				})
				.then(function (blob) {
					var objectUrl = URL.createObjectURL(blob)
					var a = document.createElement('a')
					a.href = objectUrl
					a.download = name
					document.body.appendChild(a)
					a.click()
					document.body.removeChild(a)
					// Not in the same tick: some browsers abort a blob download
					// whose URL is revoked straight after the click.
					window.setTimeout(function () {
						URL.revokeObjectURL(objectUrl)
					}, 60000)
				})
				.catch(function (err) {
					console.error('Error downloading ' + url + ':', err)
					notify(failure)
				})
		}

		/**
		 * The display name of the set the editor works on.
		 *
		 * @return {string} Its name, or its id when the page has no name for it.
		 */
		function editedTokenSetName() {
			var id = editedTokenSetId()
			var ts = tokenSetsData[id]
			return ts && ts.name ? ts.name : id
		}

		/**
		 * Say what an overrides action does before it is taken.
		 *
		 * @param {string}        id           The overlay's element id.
		 * @param {string}        title        The dialog heading.
		 * @param {Array<string>} paragraphs   What the action does, one paragraph each.
		 * @param {string}        confirmLabel The label of the button that goes ahead.
		 * @param {Function}      proceed      Called when the admin confirms.
		 *
		 * @return {void}
		 */
		function confirmOverridesAction(
			id,
			title,
			paragraphs,
			confirmLabel,
			proceed,
		) {
			var html =
				'<div class="nldesign-dialog-overlay" id="'
				+ id
				+ '">'
				+ '<div class="nldesign-dialog nldesign-dialog--small">'
				+ '<h3>'
				+ escapeHtml(title)
				+ '</h3>'
				+ paragraphs
					.map(function (text) {
						return (
							'<p class="settings-hint">' + escapeHtml(text) + '</p>'
						)
					})
					.join('')
				+ '<div class="nldesign-dialog-actions">'
				+ '<button type="button" class="nldesign-dialog-cancel">'
				+ escapeHtml(t('thematiq', 'Cancel'))
				+ '</button>'
				+ '<button type="button" class="nldesign-dialog-confirm nldesign-btn--primary">'
				+ escapeHtml(confirmLabel)
				+ '</button>'
				+ '</div>'
				+ '</div>'
				+ '</div>'

			document.body.insertAdjacentHTML('beforeend', html)
			var overlay = document.getElementById(id)

			function close() {
				overlay.remove()
			}

			makeDialogAccessible(overlay, close)

			overlay
				.querySelector('.nldesign-dialog-cancel')
				.addEventListener('click', close)
			overlay
				.querySelector('.nldesign-dialog-confirm')
				.addEventListener('click', function () {
					close()
					proceed()
				})
		}

		function confirmExportOverrides() {
			confirmOverridesAction(
				'nldesign-export-overrides-overlay',
				t(
					'thematiq',
					'Download the overrides of {name}?',
					{ name: editedTokenSetName() },
					undefined,
					{ escape: false },
				),
				[
					t(
						'thematiq',
						'This downloads only the values saved for this theme on top of its token set, as custom-overrides.css. Unsaved changes are not in it.',
					),
					t(
						'thematiq',
						'It is not a complete theme. Use Export as token set to hand the whole theme on, or the configuration bundle to move the complete configuration.',
					),
				],
				t('thematiq', 'Download'),
				exportOverrides,
			)
		}

		function confirmImportOverrides() {
			confirmOverridesAction(
				'nldesign-import-overrides-overlay',
				t(
					'thematiq',
					'Upload overrides into {name}?',
					{ name: editedTokenSetName() },
					undefined,
					{ escape: false },
				),
				[
					t(
						'thematiq',
						'The values in the file replace every value saved for this theme, straight away. Values the editor does not know are skipped, and unsaved changes are lost.',
					),
					t(
						'thematiq',
						'To add a whole theme as a new token set, use Custom token sets further down instead.',
					),
				],
				t('thematiq', 'Choose file'),
				function () {
					document.getElementById('nldesign-import-input').click()
				},
			)
		}

		/**
		 * Download the saved overrides of the edited set.
		 *
		 * @return {void}
		 */
		function exportOverrides() {
			downloadWithToken(
				overridesUrl('/export', editedTokenSetId()),
				'custom-overrides.css',
				t('thematiq', 'The overrides could not be downloaded.'),
			)
		}

		function importOverrides(file) {
			var formData = new FormData()
			formData.append('file', file)

			fetch(overridesUrl('/import', editedTokenSetId()), {
				method: 'POST',
				headers: { requesttoken: OC.requestToken },
				body: formData,
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					var resultEl = document.getElementById('nldesign-import-result')
					if (data.status === 'ok') {
						if (resultEl !== null) {
							resultEl.textContent = t(
								'thematiq',
								'{imported} tokens imported, {skipped} tokens skipped (not recognized)',
							)
								.replace('{imported}', data.imported)
								.replace('{skipped}', data.skipped)
							resultEl.style.display = 'block'
							setTimeout(function () {
								resultEl.style.display = 'none'
							}, 8000)
						}
						initTokenEditor()
					} else {
						notify(t('thematiq', 'Import failed:') + (data.error || ''))
					}
				})
				.catch(function (err) {
					console.error('Error importing overrides:', err)
					notify(t('thematiq', 'Import failed.'))
				})
		}

		/* ==========================================================================
		 * TOKEN SET APPLY DIALOG
		 * ========================================================================== */

		// publishMode (default false): when true, the dialog's confirmation
		// promotes the ALREADY-ACTIVE session preview (POST /settings/preview/publish)
		// instead of applying newTokenSetId instance-wide directly (POST
		// /settings/tokenset) — the banner/settings-panel Publish control's path.
		function openTokenSetApplyDialog(
			newTokenSetId,
			prevTokenSetId,
			publishMode,
		) {
			var preview = fetch(
				OC.generateUrl(
					'/apps/thematiq/settings/tokenset-preview/'
						+ encodeURIComponent(newTokenSetId),
				),
				{
					headers: { requesttoken: OC.requestToken },
				},
			).then(function (r) {
				return r.json()
			})

			// Core theming is read alongside the token preview so the apply
			// dialog can carry the sync as a section of itself — one confirm —
			// instead of opening a second modal afterwards. A failed read only
			// means the section is absent; the token apply still works.
			var theming = fetchCurrentTheming().catch(function () {
				return null
			})

			// What the admin saved for the set outranks its file on the page, so
			// the comparison is made against the set as saved. Against the file
			// alone, every switch back to a set pinned the file's values over
			// the ones saved for it.
			var saved =
				newTokenSetId === STOCK_TOKEN_SET
					? Promise.resolve({})
					: fetch(overridesUrl('', newTokenSetId), {
							headers: { requesttoken: OC.requestToken },
						})
							.then(function (r) {
								return r.json()
							})
							.then(function (existing) {
								return existing.overrides || {}
							})
							.catch(function () {
								return {}
							})

			Promise.all([preview, theming, saved])
				.then(function (results) {
					var data = results[0]
					var themingPlan = computeThemingPlan(
						tokenSetsData[newTokenSetId],
						results[1],
					)
					if (data.error !== undefined) {
						saveTokenSet(newTokenSetId, publishMode)
						return
					}
					var newValues = Object.assign({}, data.resolved || {})
					Object.keys(results[2]).forEach(function (name) {
						if (newValues[name] !== undefined) {
							newValues[name] = results[2][name]
						}
					})
					// A set that carries a primary colour brings it through the
					// theming sync, and Nextcloud derives the whole primary family
					// from it. Pinned here as well, the family outranked Nextcloud
					// with `!important`, so a primary chosen in Nextcloud's own
					// theming never reached anything painted from it.
					var setTheming = (tokenSetsData[newTokenSetId] || {}).theming
					if (setTheming && setTheming.primary_color) {
						Object.keys(newValues).forEach(function (name) {
							if (isPrimaryFamily(name) === true) {
								delete newValues[name]
							}
						})
					}
					var rootStyle = getComputedStyle(document.documentElement)
					var changes = []
					Object.keys(newValues).forEach(function (name) {
						var currentVal = rootStyle.getPropertyValue(name).trim()
						var newVal = newValues[name].trim()
						if (currentVal !== newVal && newVal !== '') {
							changes.push({
								name: name,
								current: currentVal,
								newVal: newVal,
							})
						}
					})
					if (changes.length === 0) {
						saveTokenSet(newTokenSetId, publishMode)
						return
					}
					showApplyDialog(
						newTokenSetId,
						prevTokenSetId,
						changes,
						publishMode,
						themingPlan,
					)
				})
				.catch(function (err) {
					console.error('Error fetching token set preview:', err)
					saveTokenSet(newTokenSetId, publishMode)
				})
		}

		function showApplyDialog(
			newTokenSetId,
			prevTokenSetId,
			changes,
			publishMode,
			themingPlan,
		) {
			var plan = themingPlan || { mode: 'none', diffs: [], payload: null }
			var existing = document.getElementById('nldesign-apply-dialog-overlay')
			if (existing !== null) {
				existing.remove()
			}

			var rowsHtml = ''
			changes.forEach(function (change) {
				var meta = tokenRegistry[change.name] || {
					label: change.name,
					type: 'text',
				}
				var isColor = meta.type === 'color'
				var currentDisp = isColor
					? '<span class="nldesign-apply-swatch" style="background:'
						+ escapeHtml(change.current)
						+ '"></span>'
						+ escapeHtml(change.current)
					: escapeHtml(change.current)
				var newDisp = isColor
					? '<span class="nldesign-apply-swatch" style="background:'
						+ escapeHtml(change.newVal)
						+ '"></span>'
						+ escapeHtml(change.newVal)
					: escapeHtml(change.newVal)
				rowsHtml +=
					'<tr>'
					+ '<td><input type="checkbox" class="nldesign-apply-check" data-token="'
					+ escapeHtml(change.name)
					+ '" checked></td>'
					+ '<td><span title="'
					+ escapeHtml(change.name)
					+ '">'
					+ escapeHtml(meta.label)
					+ '</span></td>'
					+ '<td>'
					+ currentDisp
					+ '</td>'
					+ '<td>'
					+ newDisp
					+ '</td>'
					+ '</tr>'
			})

			var html =
				'<div id="nldesign-apply-dialog-overlay" class="nldesign-dialog-overlay">'
				+ '<div class="nldesign-dialog">'
				+ '<h3>'
				+ escapeHtml(
					t('thematiq', 'Apply token set: {name}').replace(
						'{name}',
						newTokenSetId,
					),
				)
				+ '</h3>'
				+ buildTokenSetWarningsHtml(newTokenSetId)
				// A set other than the running switch's would be put back within
				// five minutes; say so before it is applied.
				+ (runningSwitch !== null && runningSwitch.tokenSet !== newTokenSetId
					? '<p class="settings-hint nldesign-apply-switch-warning">'
						+ escapeHtml(runningSwitchText())
						+ '</p>'
					: '')
				+ '<p class="settings-hint">'
				+ escapeHtml(
					t(
						'thematiq',
						'These values would change. check which ones to apply to your custom overrides.',
					),
				)
				+ '</p>'
				+ '<div style="margin-bottom:8px">'
				+ '<button class="nldesign-apply-dialog-toggle" id="nldesign-apply-select-all">'
				+ escapeHtml(t('thematiq', 'Select all'))
				+ '</button>'
				+ ' / '
				+ '<button class="nldesign-apply-dialog-toggle" id="nldesign-apply-deselect-all">'
				+ escapeHtml(t('thematiq', 'Deselect all'))
				+ '</button>'
				+ '</div>'
				+ '<table class="nldesign-apply-dialog-table"><thead><tr>'
				+ '<th></th>'
				+ '<th>'
				+ escapeHtml(t('thematiq', 'Token'))
				+ '</th>'
				+ '<th>'
				+ escapeHtml(t('thematiq', 'Current'))
				+ '</th>'
				+ '<th>'
				+ escapeHtml(t('thematiq', 'New'))
				+ '</th>'
				+ '</tr></thead><tbody>'
				+ rowsHtml
				+ '</tbody></table>'
				// Core theming as a SECTION of this dialog, checked by default,
				// so there is one confirm — not a second modal afterwards.
				+ (plan.mode !== 'none'
					? '<div class="nldesign-apply-theming" id="nldesign-apply-theming">'
						+ '<label class="nldesign-apply-theming__label">'
						+ '<input type="checkbox" id="nldesign-apply-theming-check" checked> '
						+ escapeHtml(
							plan.mode === 'reset'
								? t(
										'thematiq',
										'Also reset Nextcloud theming to its defaults (login page, e-mails, mobile apps)',
									)
								: t(
										'thematiq',
										'Also update Nextcloud theming (login page, e-mails, mobile apps)',
									),
						)
						+ '</label>'
						+ '<table class="nldesign-dialog-table"><thead><tr><th>'
						+ escapeHtml(t('thematiq', 'Setting'))
						+ '</th><th>'
						+ escapeHtml(t('thematiq', 'Current'))
						+ '</th><th>'
						+ escapeHtml(t('thematiq', 'Proposed'))
						+ '</th></tr></thead><tbody>'
						+ themingRowsHtml(plan.diffs)
						+ '</tbody></table>'
						+ '</div>'
					: '')
				+ '<div class="nldesign-dialog-actions">'
				+ '<button class="nldesign-dialog-cancel">'
				+ escapeHtml(t('thematiq', 'Cancel'))
				+ '</button>'
				+ '<button class="nldesign-dialog-confirm">'
				+ escapeHtml(t('thematiq', 'Apply selected'))
				+ '</button>'
				+ '</div>'
				+ '</div>'
				+ '</div>'

			document.body.insertAdjacentHTML('beforeend', html)
			var overlay = document.getElementById('nldesign-apply-dialog-overlay')

			makeDialogAccessible(overlay, function () {
				cancelDialog()
			})

			function updateApplyPreview() {
				changes.forEach(function (change) {
					var cb = overlay.querySelector(
						'.nldesign-apply-check[data-token="' + change.name + '"]',
					)
					if (cb !== null && cb.checked === true) {
						document.documentElement.style.setProperty(
							change.name,
							change.newVal,
						)
					} else {
						document.documentElement.style.setProperty(
							change.name,
							change.current,
						)
					}
				})
			}

			overlay.querySelectorAll('.nldesign-apply-check').forEach(function (cb) {
				cb.addEventListener('change', updateApplyPreview)
			})
			updateApplyPreview()

			document
				.getElementById('nldesign-apply-select-all')
				.addEventListener('click', function () {
					overlay
						.querySelectorAll('.nldesign-apply-check')
						.forEach(function (cb) {
							cb.checked = true
						})
					updateApplyPreview()
				})
			document
				.getElementById('nldesign-apply-deselect-all')
				.addEventListener('click', function () {
					overlay
						.querySelectorAll('.nldesign-apply-check')
						.forEach(function (cb) {
							cb.checked = false
						})
					updateApplyPreview()
				})

			// True from the confirm click until the new theme is on the page.
			// While it is set, no dismissal path may run: the dialog now stays
			// up THROUGH the apply, and cancelling half way would put the
			// dropdown back on the old set while the new one keeps loading.
			var applying = false

			function cancelDialog() {
				if (applying === true) {
					return
				}
				changes.forEach(function (c) {
					document.documentElement.style.removeProperty(c.name)
				})
				if (tokenSetSelect !== null) {
					tokenSetSelect.value = prevTokenSetId
					tokenSetSelect.dataset.previousValue = prevTokenSetId
					updatePreview(prevTokenSetId)
				}
				closeDialogOverlay(overlay)
			}

			overlay
				.querySelector('.nldesign-dialog-cancel')
				.addEventListener('click', cancelDialog)
			overlay.addEventListener('click', function (e) {
				if (e.target === overlay) {
					cancelDialog()
				}
			})

			overlay
				.querySelector('.nldesign-dialog-confirm')
				.addEventListener('click', function () {
					var btn = this
					var cancelBtn = overlay.querySelector('.nldesign-dialog-cancel')
					applying = true
					btn.disabled = true
					btn.textContent = t('thematiq', 'Applying…')
					if (cancelBtn !== null) {
						cancelBtn.disabled = true
					}

					// Read at click time: what the admin confirmed is what runs,
					// whatever happens to the dialog while the apply is in flight.
					var syncEl = overlay.querySelector(
						'#nldesign-apply-theming-check',
					)
					var syncChecked = syncEl !== null && syncEl.checked === true

					var toApply = {}
					overlay
						.querySelectorAll('.nldesign-apply-check')
						.forEach(function (cb) {
							if (cb.checked === true) {
								var change = changes.find(function (c) {
									return c.name === cb.dataset.token
								})
								if (change !== undefined) {
									toApply[cb.dataset.token] = change.newVal
								}
							}
						})

					// Nothing is pinned into the stock set. Its overrides file is
					// the Nextcloud theme ITSELF being changed, which only the
					// editor does and only past its warning; values pinned here on
					// the way back to stock are what used to keep a stale blue on
					// the page through every reset.
					var pinned =
						newTokenSetId === STOCK_TOKEN_SET
							? Promise.resolve({ status: 'ok' })
							: fetch(overridesUrl('', newTokenSetId), {
									headers: { requesttoken: OC.requestToken },
								})
									.then(function (r) {
										return r.json()
									})
									.then(function (existingData) {
										var merged = Object.assign(
											{},
											existingData.overrides || {},
											toApply,
										)
										return fetch(
											OC.generateUrl(
												'/apps/thematiq/settings/overrides',
											),
											{
												method: 'POST',
												headers: {
													'Content-Type':
														'application/json',
													requesttoken: OC.requestToken,
												},
												body: JSON.stringify({
													overrides: merged,
													tokenSet: newTokenSetId,
												}),
											},
										)
									})
									.then(function (r) {
										return r.json()
									})

					pinned
						.then(function (saveData) {
							if (saveData.status !== 'ok') {
								throw new Error(saveData.error || 'Save failed')
							}
							return commitTokenSetChange(
								newTokenSetId,
								publishMode === true,
							)
						})
						.then(function (tsData) {
							if (tsData.status !== 'ok') {
								throw new Error(
									tsData.error || 'Token set change failed',
								)
							}
							if (tokenSetSelect !== null && publishMode !== true) {
								tokenSetSelect.dataset.previousValue = newTokenSetId
							}

							// The dialog stays open across this step: `swap()` only
							// settles once every new <link> has fired `load`, so the
							// admin keeps looking at "Applying…" until the theme they
							// picked is actually in effect behind the dialog.
							//
							// The dialog's live preview wrote the chosen values inline
							// on <html>; they are KEPT until the swap has settled, so
							// the page cannot flash back to the old set while the new
							// stylesheets are still on the wire. The overrides just
							// written to custom-overrides.css are re-fetched, and the
							// set's own run is swapped in.
							refreshCustomOverridesLink()
							return applyLayersFor(newTokenSetId)
						})
						.then(function (swapped) {
							// The real stylesheets take over from the inline preview.
							changes.forEach(function (c) {
								document.documentElement.style.removeProperty(c.name)
							})
							initTokenEditor()

							if (publishMode === true) {
								endPreviewOnPage()
							}

							// A rolled-back swap has to be said out loud on EVERY
							// branch below, not only the one with no core-theming work
							// to report. The POST that changed the active set has
							// already succeeded and the inline preview values were
							// stripped just above, so whichever message this step
							// produces, the admin is looking at the OLD theme until
							// they reload.
							//
							// Appended as a second WHOLE sentence rather than built
							// from fragments, so it stands on its own in every locale.
							var reloadHint =
								swapped === true
									? ''
									: ' '
										+ t(
											'thematiq',
											'Reload the page to see changes.',
										)

							// Core theming rides on the same confirm. The sync used to
							// be unreachable from this path and, once reachable, was a
							// second modal;
							// it is now the checked section above, applied on this same
							// confirm while the dialog is still up.
							if (plan.mode !== 'none' && syncChecked === true) {
								// Its own catch: the token set is already applied by
								// now, so a theming failure must not be reported as
								// "Failed to apply token set" — which is exactly what
								// a 405 on the reset route produced, sending an admin
								// looking for a problem in the set. It resolves to the
								// message instead of notifying, because the dialog is
								// still up and closes on the step after this one.
								return applyThemingPlan(plan)
									.then(function () {
										return (
											(plan.mode === 'reset'
												? t(
														'thematiq',
														'Applied. Nextcloud theming reset to its defaults.',
													)
												: t(
														'thematiq',
														'Applied. Nextcloud theming updated.',
													)) + reloadHint
										)
									})
									.catch(function (themingError) {
										console.error(
											'Error syncing Nextcloud theming:',
											themingError,
										)
										return (
											t(
												'thematiq',
												'Theme applied, but updating Nextcloud theming failed.',
											) + reloadHint
										)
									})
							}

							return t('thematiq', 'Applied.') + reloadHint
						})
						.then(function (message) {
							// Only now: the set's stylesheets have loaded, core
							// theming is in, and the page behind the dialog is the
							// theme the admin picked.
							applying = false
							closeDialogOverlay(overlay)
							notify(message)
						})
						.catch(function (err) {
							// The dialog is still open on every failure path, so the
							// admin can read the error and press Apply again.
							applying = false
							btn.disabled = false
							btn.textContent = t('thematiq', 'Apply selected')
							if (cancelBtn !== null) {
								cancelBtn.disabled = false
							}
							console.error('Error applying token set:', err)
							notify(t('thematiq', 'Failed to apply token set.'))
						})
				})
		}

		/* ==========================================================================
		 * HELPERS
		 * ========================================================================== */

		function normaliseColorForPicker(value) {
			// The pure #RRGGBB / #RGB / empty cases are handled by the extracted
			// helper; it returns null for values needing browser colour resolution
			// (named colours, rgb()/hsl()), which we resolve via a canvas below.
			if (typeof TT.normaliseColorForPicker === 'function') {
				var pure = TT.normaliseColorForPicker(value)
				if (pure !== null) {
					return pure
				}
			} else {
				if (value === undefined || value === null || value === '') {
					return '#000000'
				}
				var vv = value.trim()
				if (/^#[0-9a-fA-F]{6}$/.test(vv) === true) {
					return vv
				}
				if (/^#[0-9a-fA-F]{3}$/.test(vv) === true) {
					return '#' + vv[1] + vv[1] + vv[2] + vv[2] + vv[3] + vv[3]
				}
			}
			var v = String(value).trim()
			try {
				var canvas = document.createElement('canvas')
				canvas.width = canvas.height = 1
				var ctx = canvas.getContext('2d')
				ctx.fillStyle = v
				ctx.fillRect(0, 0, 1, 1)
				var d = ctx.getImageData(0, 0, 1, 1).data
				return (
					'#'
					+ ('0' + d[0].toString(16)).slice(-2)
					+ ('0' + d[1].toString(16)).slice(-2)
					+ ('0' + d[2].toString(16)).slice(-2)
				)
			} catch (e) {
				return '#000000'
			}
		}

		// Initialise token editor on page load.
		initTokenEditor()

		/* ==========================================================================
		 * THEMING PER APP — exclude individual apps from nldesign theming
		 * ========================================================================== */

		// Render the checkbox list (checked = themed) from GET /settings/app-theming.
		function initAppTheming() {
			var listEl = document.getElementById('nldesign-app-theming-list')
			var saveBtn = document.getElementById('nldesign-app-theming-save')
			if (listEl === null || saveBtn === null) {
				return
			}

			fetch(OC.generateUrl('/apps/thematiq/settings/app-theming'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					renderAppThemingList(listEl, (data && data.apps) || [])
				})
				.catch(function (err) {
					console.error('Error loading app theming:', err)
					listEl.textContent = t('thematiq', 'Failed to load apps.')
				})

			saveBtn.addEventListener('click', saveAppTheming)
		}

		// Build one labelled checkbox per app; checked means "themed".
		// Compact collapsed dropdown with search. The checkbox data model (one
		// input[data-app-id] per app, checked === themed) is preserved inside the
		// panel so saveAppTheming() keeps working unchanged.
		function renderAppThemingList(listEl, apps) {
			listEl.innerHTML = ''
			if (apps.length === 0) {
				listEl.textContent = t('thematiq', 'No apps available.')
				return
			}

			var dropdown = document.createElement('div')
			dropdown.className = 'nldesign-app-dropdown'

			var trigger = document.createElement('button')
			trigger.type = 'button'
			trigger.className = 'nldesign-app-dropdown-trigger'
			// The panel holds a search field and a list of checkboxes, so it is
			// a non-modal dialog, not a menu or a listbox: `aria-haspopup` names
			// that, and the trigger's own text ("3 of 12 apps themed") labels it.
			trigger.id = 'nldesign-app-dropdown-trigger'
			trigger.setAttribute('aria-haspopup', 'dialog')
			trigger.setAttribute('aria-expanded', 'false')
			trigger.setAttribute('aria-controls', 'nldesign-app-dropdown-panel')
			var triggerLabel = document.createElement('span')
			trigger.appendChild(triggerLabel)

			var panel = document.createElement('div')
			panel.className = 'nldesign-app-dropdown-panel'
			panel.id = 'nldesign-app-dropdown-panel'
			panel.setAttribute('role', 'dialog')
			panel.setAttribute('aria-labelledby', trigger.id)

			var searchWrap = document.createElement('div')
			searchWrap.className = 'nldesign-app-dropdown-search'
			var search = document.createElement('input')
			search.type = 'search'
			search.placeholder = t('thematiq', 'Search apps…')
			search.setAttribute('aria-label', t('thematiq', 'Search apps'))
			searchWrap.appendChild(search)

			var optList = document.createElement('div')
			optList.className = 'nldesign-app-dropdown-list'

			function updateTriggerLabel() {
				var boxes = optList.querySelectorAll(
					'input[type="checkbox"][data-app-id]',
				)
				var themed = window.NldesignAppTheming.countThemed(
					Array.prototype.map.call(boxes, function (b) {
						return { checked: b.checked }
					}),
				).themed
				triggerLabel.textContent = t(
					'thematiq',
					'{themed} of {total} apps themed',
					{ themed: themed, total: boxes.length },
				)
			}

			apps.forEach(function (app) {
				var opt = document.createElement('div')
				opt.className = 'nldesign-app-option'
				opt.setAttribute(
					'data-app-name',
					String(app.name || app.id).toLowerCase(),
				)

				var cb = document.createElement('input')
				cb.type = 'checkbox'
				cb.className = 'checkbox'
				cb.id = 'nldesign-app-theming-' + app.id
				cb.setAttribute('data-app-id', app.id)
				cb.checked = app.themed !== false
				cb.addEventListener('change', updateTriggerLabel)

				var label = document.createElement('label')
				label.setAttribute('for', cb.id)
				label.textContent = app.name || app.id

				opt.appendChild(cb)
				opt.appendChild(label)
				optList.appendChild(opt)
			})

			search.addEventListener('input', function () {
				var q = search.value.trim().toLowerCase()
				optList
					.querySelectorAll('.nldesign-app-option')
					.forEach(function (opt) {
						opt.hidden = !window.NldesignAppTheming.matchesAppSearch(
							opt.getAttribute('data-app-name'),
							q,
						)
					})
			})

			function openDropdown() {
				dropdown.classList.add('open')
				trigger.setAttribute('aria-expanded', 'true')
				search.focus()
			}

			function closeDropdown(restoreFocus) {
				dropdown.classList.remove('open')
				trigger.setAttribute('aria-expanded', 'false')
				if (restoreFocus === true) {
					trigger.focus()
				}
			}

			trigger.addEventListener('click', function (e) {
				e.stopPropagation()
				if (dropdown.classList.contains('open')) {
					closeDropdown(false)
				} else {
					openDropdown()
				}
			})
			document.addEventListener('click', function (e) {
				if (!dropdown.contains(e.target)) {
					closeDropdown(false)
				}
			})

			// Keyboard users need a way to dismiss the panel without a mouse click
			// outside it; Escape closes it and returns focus to the trigger (WCAG
			// 2.1.1 Keyboard).
			dropdown.addEventListener('keydown', function (e) {
				if (
					(e.key === 'Escape' || e.keyCode === 27)
					&& dropdown.classList.contains('open')
				) {
					e.preventDefault()
					closeDropdown(true)
				}
			})

			// The panel is not modal, so Tab may leave it. When focus moves
			// outside the dropdown the panel closes, the keyboard twin of the
			// click-outside handler above, and focus stays where the user sent it.
			dropdown.addEventListener('focusout', function (e) {
				if (
					e.relatedTarget !== null
					&& !dropdown.contains(e.relatedTarget)
				) {
					closeDropdown(false)
				}
			})

			panel.appendChild(searchWrap)
			panel.appendChild(optList)
			dropdown.appendChild(trigger)
			dropdown.appendChild(panel)
			listEl.appendChild(dropdown)
			updateTriggerLabel()
		}

		// Collect unchecked apps as the exclusion list and POST it.
		function saveAppTheming() {
			var listEl = document.getElementById('nldesign-app-theming-list')
			var feedback = document.getElementById('nldesign-app-theming-feedback')
			if (listEl === null) {
				return
			}

			var disabledApps = window.NldesignAppTheming.buildDisabledAppsPayload(
				Array.prototype.map.call(
					listEl.querySelectorAll('input[type="checkbox"][data-app-id]'),
					function (cb) {
						return {
							id: cb.getAttribute('data-app-id'),
							checked: cb.checked,
						}
					},
				),
			)

			fetch(OC.generateUrl('/apps/thematiq/settings/app-theming'), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({ disabledApps: disabledApps }),
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					if (data && data.status === 'ok') {
						if (feedback !== null) {
							feedback.textContent = t(
								'thematiq',
								'App theming saved. Reload an affected app to see changes.',
							)
						}
						notify(
							t(
								'thematiq',
								'App theming saved. Reload an affected app to see changes.',
							),
						)
					} else {
						notify(t('thematiq', 'Failed to save app theming.'))
					}
				})
				.catch(function (err) {
					console.error('Error saving app theming:', err)
					notify(t('thematiq', 'Failed to save app theming.'))
				})
		}

		// Initialise the per-app theming panel on page load.
		initAppTheming()

		/* ==========================================================================
		 * GROUP THEMING — map Nextcloud groups to token sets (multi-tenant huisstijl)
		 * openspec/specs/per-group-theming/spec.md
		 * ========================================================================== */

		// In-memory ordered mapping rows: [{ group: string, tokenSet: string }, ...].
		// Array order IS priority order — mirrors the server's storage shape
		// exactly so there is nothing extra to keep in sync.
		var groupThemingRows = []
		var groupThemingGroups = []
		var groupThemingTokenSets = []

		function initGroupTheming() {
			var listEl = document.getElementById('nldesign-group-theming-list')
			var addBtn = document.getElementById('nldesign-group-theming-add')
			var saveBtn = document.getElementById('nldesign-group-theming-save')
			if (listEl === null || addBtn === null || saveBtn === null) {
				return
			}

			fetch(OC.generateUrl('/apps/thematiq/settings/group-theming'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					groupThemingGroups = (data && data.groups) || []
					groupThemingTokenSets = (data && data.tokenSets) || []
					groupThemingRows = ((data && data.mapping) || []).map(
						toGroupThemingRow,
					)
					renderGroupThemingList()
				})
				.catch(function (err) {
					console.error('Error loading group theming:', err)
					listEl.textContent = t(
						'thematiq',
						'Failed to load group mappings.',
					)
				})

			addBtn.addEventListener('click', function () {
				var defaultGroup =
					groupThemingGroups.length > 0 ? groupThemingGroups[0].id : ''
				var defaultTokenSet =
					groupThemingTokenSets.length > 0
						? groupThemingTokenSets[0].id
						: ''
				groupThemingRows.push({
					group: defaultGroup,
					tokenSet: defaultTokenSet,
				})
				renderGroupThemingList()

				// Move focus to the newly added row's group select.
				var rows = listEl.querySelectorAll('.nldesign-group-theming-row')
				var last = rows[rows.length - 1]
				if (last) {
					var groupSelect = last.querySelector('[data-field="group"]')
					if (groupSelect) {
						groupSelect.focus()
					}
				}
			})

			saveBtn.addEventListener('click', saveGroupTheming)
		}

		// Render one row per mapping entry: group select, token-set select,
		// move-up / move-down (keyboard-operable, no drag-and-drop), remove.
		function renderGroupThemingList(focusMoveButtonIndex) {
			var listEl = document.getElementById('nldesign-group-theming-list')
			if (listEl === null) {
				return
			}

			listEl.innerHTML = ''

			if (groupThemingRows.length === 0) {
				var empty = document.createElement('p')
				empty.className = 'settings-hint'
				empty.textContent = t('thematiq', 'No group mappings configured.')
				listEl.appendChild(empty)
				return
			}

			groupThemingRows.forEach(function (row, index) {
				var rowEl = document.createElement('div')
				rowEl.className = 'nldesign-group-theming-row'

				var groupSelect = document.createElement('select')
				groupSelect.setAttribute('data-field', 'group')
				groupSelect.setAttribute('aria-label', t('thematiq', 'Group'))
				groupThemingGroups.forEach(function (g) {
					var opt = document.createElement('option')
					opt.value = g.id
					opt.textContent = g.displayName || g.id
					if (g.id === row.group) {
						opt.selected = true
					}
					groupSelect.appendChild(opt)
				})
				groupSelect.addEventListener('change', function () {
					groupThemingRows[index].group = groupSelect.value
				})

				var tokenSetSelect = document.createElement('select')
				tokenSetSelect.setAttribute('data-field', 'tokenSet')
				tokenSetSelect.setAttribute('aria-label', t('thematiq', 'Token set'))
				groupThemingTokenSets.forEach(function (ts) {
					var opt = document.createElement('option')
					opt.value = ts.id
					opt.textContent = ts.name || ts.id
					if (ts.id === row.tokenSet) {
						opt.selected = true
					}
					tokenSetSelect.appendChild(opt)
				})
				tokenSetSelect.addEventListener('change', function () {
					groupThemingRows[index].tokenSet = tokenSetSelect.value
				})

				var moveUpBtn = document.createElement('button')
				moveUpBtn.type = 'button'
				moveUpBtn.className = 'nldesign-group-theming-move-up'
				moveUpBtn.setAttribute(
					'aria-label',
					t('thematiq', 'Move mapping up'),
				)
				moveUpBtn.textContent = '▲'
				moveUpBtn.disabled = index === 0
				moveUpBtn.addEventListener('click', function () {
					moveGroupThemingRow(index, -1)
				})

				var moveDownBtn = document.createElement('button')
				moveDownBtn.type = 'button'
				moveDownBtn.className = 'nldesign-group-theming-move-down'
				moveDownBtn.setAttribute(
					'aria-label',
					t('thematiq', 'Move mapping down'),
				)
				moveDownBtn.textContent = '▼'
				moveDownBtn.disabled = index === groupThemingRows.length - 1
				moveDownBtn.addEventListener('click', function () {
					moveGroupThemingRow(index, 1)
				})

				var removeBtn = document.createElement('button')
				removeBtn.type = 'button'
				removeBtn.className = 'nldesign-group-theming-remove'
				removeBtn.setAttribute('aria-label', t('thematiq', 'Remove mapping'))
				removeBtn.textContent = '×'
				removeBtn.addEventListener('click', function () {
					groupThemingRows.splice(index, 1)
					renderGroupThemingList()
				})

				rowEl.appendChild(groupSelect)
				rowEl.appendChild(tokenSetSelect)
				rowEl.appendChild(renderGroupDelegation(index))
				rowEl.appendChild(moveUpBtn)
				rowEl.appendChild(moveDownBtn)
				rowEl.appendChild(removeBtn)
				listEl.appendChild(rowEl)
			})

			// Restore focus to the moved row's move-up button after a reorder
			// (WCAG 2.1.1 Keyboard / 2.4.3 Focus Order — no keyboard trap, no
			// lost focus on re-render).
			if (typeof focusMoveButtonIndex === 'number') {
				var focusRows = listEl.querySelectorAll(
					'.nldesign-group-theming-row',
				)
				var target = focusRows[focusMoveButtonIndex]
				if (target) {
					// A row moved to a boundary has its corresponding move button
					// disabled, and a disabled control cannot hold focus — focus
					// would fall back to <body>, losing the user's place. Focus the
					// nearest still-operable control on the moved row instead, so
					// keyboard users always land on the row they just moved.
					var btn = target.querySelector('.nldesign-group-theming-move-up')
					if (btn === null || btn.disabled === true) {
						btn = target.querySelector(
							'.nldesign-group-theming-move-down',
						)
					}
					if (btn !== null && btn.disabled === false) {
						btn.focus()
					}
				}
			}
		}

		// A mapping entry as the list keeps it. Delegation (an administrator
		// lets the group's subadmins choose from allowed sets) is optional:
		// an entry without it reads as not delegated.
		// openspec/specs/per-group-theming/spec.md
		function toGroupThemingRow(entry) {
			return {
				group: entry.group,
				tokenSet: entry.tokenSet,
				delegated: entry.delegated === true,
				allowedTokenSets: Array.isArray(entry.allowedTokenSets)
					? entry.allowedTokenSets.slice()
					: [],
			}
		}

		// The delegate toggle and the allowed sets picker of one mapping row.
		// Turning delegation on starts the allowed list with the current set,
		// which the server requires it to hold.
		function renderGroupDelegation(index) {
			var row = groupThemingRows[index]
			if (!Array.isArray(row.allowedTokenSets)) {
				row.allowedTokenSets = []
			}
			var wrap = document.createElement('span')
			wrap.className = 'nldesign-group-theming-delegation'

			var toggleId = 'nldesign-group-theming-delegate-' + index
			var toggle = document.createElement('input')
			toggle.type = 'checkbox'
			toggle.className = 'checkbox'
			toggle.id = toggleId
			toggle.setAttribute('data-field', 'delegated')
			toggle.checked = row.delegated === true
			var toggleLabel = document.createElement('label')
			toggleLabel.setAttribute('for', toggleId)
			toggleLabel.textContent = t('thematiq', 'Subadmins choose')
			wrap.appendChild(toggle)
			wrap.appendChild(toggleLabel)

			var picker = document.createElement('select')
			picker.multiple = true
			picker.setAttribute('data-field', 'allowedTokenSets')
			picker.setAttribute(
				'aria-label',
				t('thematiq', 'Token sets the subadmins of this group can choose'),
			)
			picker.hidden = row.delegated !== true
			groupThemingTokenSets.forEach(function (ts) {
				var opt = document.createElement('option')
				opt.value = ts.id
				opt.textContent = ts.name || ts.id
				opt.selected = row.allowedTokenSets.indexOf(ts.id) !== -1
				picker.appendChild(opt)
			})
			picker.addEventListener('change', function () {
				groupThemingRows[index].allowedTokenSets = Array.prototype.filter
					.call(picker.options, function (opt) {
						return opt.selected
					})
					.map(function (opt) {
						return opt.value
					})
			})
			toggle.addEventListener('change', function () {
				groupThemingRows[index].delegated = toggle.checked
				if (
					toggle.checked
					&& groupThemingRows[index].allowedTokenSets.length === 0
				) {
					groupThemingRows[index].allowedTokenSets = [
						groupThemingRows[index].tokenSet,
					]
					Array.prototype.forEach.call(picker.options, function (opt) {
						opt.selected = opt.value === groupThemingRows[index].tokenSet
					})
				}
				picker.hidden = !toggle.checked
			})
			wrap.appendChild(picker)
			return wrap
		}

		// Swap row at `index` with its neighbour `index + direction` (direction
		// is -1 for up, +1 for down) and keep focus on the moved row's move-up
		// button at its new position.
		function moveGroupThemingRow(index, direction) {
			var target = index + direction
			if (target < 0 || target >= groupThemingRows.length) {
				return
			}

			var tmp = groupThemingRows[index]
			groupThemingRows[index] = groupThemingRows[target]
			groupThemingRows[target] = tmp

			renderGroupThemingList(target)
		}

		// POST the full ordered mapping and surface success/validation feedback.
		function saveGroupTheming() {
			var feedback = document.getElementById('nldesign-group-theming-feedback')
			var payload = groupThemingRows.map(function (row) {
				var entry = { group: row.group, tokenSet: row.tokenSet }
				if (row.delegated === true) {
					entry.delegated = true
					entry.allowedTokenSets = row.allowedTokenSets
				}
				return entry
			})

			fetch(OC.generateUrl('/apps/thematiq/settings/group-theming'), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({ mapping: payload }),
			})
				.then(function (r) {
					return r.json().then(function (data) {
						return { ok: r.ok, data: data }
					})
				})
				.then(function (result) {
					var data = result.data

					if (result.ok === true && data && data.status === 'ok') {
						groupThemingRows = (data.mapping || []).map(
							toGroupThemingRow,
						)
						renderGroupThemingList()
						if (feedback !== null) {
							feedback.textContent = t(
								'thematiq',
								'Group theming saved.',
							)
						}
						notify(t('thematiq', 'Group theming saved.'))
						return
					}

					if (data && data.error === 'invalid_mapping') {
						var entryGroup = (data.entry && data.entry.group) || '?'
						var entryTokenSet =
							(data.entry && data.entry.tokenSet) || '?'
						var message = t(
							'thematiq',
							'Could not save mapping for group "{group}" → "{tokenSet}": {reason}',
							{
								group: entryGroup,
								tokenSet: entryTokenSet,
								reason: data.reason || '',
							},
						)
						if (feedback !== null) {
							feedback.textContent = message
						}
						notify(message)
						// Rows are left exactly as the admin edited them — no silent
						// state reset — so the offending entry stays editable.
						return
					}

					if (feedback !== null) {
						feedback.textContent = t(
							'thematiq',
							'Failed to save group theming.',
						)
					}
					notify(t('thematiq', 'Failed to save group theming.'))
				})
				.catch(function (err) {
					console.error('Error saving group theming:', err)
					notify(t('thematiq', 'Failed to save group theming.'))
				})
		}

		// Initialise the group theming panel on page load.
		initGroupTheming()

		/* ==========================================================================
		 * CUSTOM TOKEN SETS — upload / list / download / delete (eigen huisstijl)
		 * ========================================================================== */

		// Build the non-blocking warning banners for a token set, from the
		// warnings carried on the `tokenSets` initial-state payload: the WCAG
		// contrast findings and (a separate block, distinguished by
		// `kind === 'incomplete'`) the vocabulary-completeness finding.
		function buildTokenSetWarningsHtml(tokenSetId) {
			return (
				buildContrastWarningHtml(tokenSetId)
				+ buildIncompleteWarningHtml(tokenSetId)
			)
		}

		// Build the "set does not define the vocabulary the theme reads" banner.
		// Empty string for a complete set.
		function buildIncompleteWarningHtml(tokenSetId) {
			var warning = incompleteWarningFor(tokenSetId)
			if (warning === null) {
				return ''
			}
			var items = incompleteWarningLines(warning)
				.map(function (line) {
					return '<li>' + escapeHtml(line) + '</li>'
				})
				.join('')
			return (
				'<div class="nldesign-contrast-warning" role="alert">'
				+ '<strong>'
				+ escapeHtml(t('thematiq', 'Incomplete set'))
				+ '</strong>'
				+ '<p>'
				+ escapeHtml(
					t(
						'thematiq',
						'This set does not define every token the design system reads, so the missing ones fall back to the Rijkshuisstijl defaults instead of this brand.',
					),
				)
				+ '</p>'
				+ '<ul>'
				+ items
				+ '</ul>'
				+ '</div>'
			)
		}

		// Build the non-blocking contrast-warning banner for a token set, from the
		// warnings carried on the `tokenSets` initial-state payload.
		function buildContrastWarningHtml(tokenSetId) {
			var ts = tokenSetsData[tokenSetId]
			if (!ts || !ts.warnings || ts.warnings.length === 0) {
				return ''
			}
			var items = ts.warnings
				.filter(function (w) {
					// The vocabulary finding travels on the same channel but has
					// no contrast pair; it gets its own banner above.
					return w && w.kind !== 'incomplete'
				})
				.map(function (w) {
					if (w.unevaluated === true) {
						return (
							'<li>'
							+ escapeHtml(
								t(
									'thematiq',
									'{pair}: contrast could not be evaluated (non-literal color).',
								).replace('{pair}', w.pair),
							)
							+ '</li>'
						)
					}
					return (
						'<li>'
						+ escapeHtml(
							t(
								'thematiq',
								'{pair}: contrast {ratio}:1 is below the WCAG 2.1 AA threshold of {threshold}:1.',
							)
								.replace('{pair}', w.pair)
								.replace('{ratio}', w.ratio)
								.replace('{threshold}', w.threshold),
						)
						+ '</li>'
					)
				})
				.join('')
			if (items === '') {
				return ''
			}
			return (
				'<div class="nldesign-contrast-warning" role="alert">'
				+ '<strong>'
				+ escapeHtml(t('thematiq', 'WCAG 2.1 AA contrast warning'))
				+ '</strong>'
				+ '<ul>'
				+ items
				+ '</ul>'
				+ '</div>'
			)
		}

		function initCustomTokenSets() {
			var uploadBtn = document.getElementById('nldesign-upload-btn')
			var fileInput = document.getElementById('nldesign-upload-input')
			var nameInput = document.getElementById('nldesign-upload-name')
			if (uploadBtn === null || fileInput === null || nameInput === null) {
				return
			}

			uploadBtn.addEventListener('click', function () {
				if (nameInput.value.trim() === '') {
					notify(t('thematiq', 'Enter a token set name first.'))
					nameInput.focus()
					return
				}
				fileInput.click()
			})

			fileInput.addEventListener('change', function (e) {
				var file = e.target.files[0]
				if (!file) {
					return
				}
				uploadCustomTokenSet(nameInput.value.trim(), file)
				fileInput.value = ''
			})

			loadCustomTokenSets()
		}

		// Localised label for a DTCG import diagnostic `reason` code. Falls back to
		// the raw code for any future reason this script does not yet know about,
		// so a new mapper diagnostic never renders as blank.
		function reasonLabel(reason) {
			var labels = {
				'unmapped-path': t(
					'thematiq',
					'Not part of the --nldesign-* vocabulary',
				),
				'missing-type': t(
					'thematiq',
					'No $type could be resolved (never guessed)',
				),
				'unsupported-color-space': t('thematiq', 'Unsupported color space'),
				'unsupported-value-shape': t('thematiq', 'Unsupported value shape'),
				'alias-cycle': t('thematiq', 'Alias cycle detected'),
				'alias-target-missing': t('thematiq', 'Alias target does not exist'),
				'alias-depth-exceeded': t(
					'thematiq',
					'Alias chain too deep (more than 10 hops)',
				),
				'duplicate-target': t(
					'thematiq',
					'Another token already maps to this target',
				),
			}
			return labels[reason] || reason
		}

		// Inline copy of tokenTransforms' groupDiagnosticsByReason, used when the
		// transforms module is not present on `window` (the app template loads it
		// first, but admin.js must degrade rather than silently render nothing).
		// Keep in sync with js/lib/tokenTransforms.js.
		function groupDiagnosticsByReasonFallback(entries) {
			if (!Array.isArray(entries) || entries.length === 0) {
				return []
			}

			var byReason = {}
			entries.forEach(function (entry) {
				var reason = (entry && entry.reason) || 'unknown'
				if (byReason[reason] === undefined) {
					byReason[reason] = []
				}
				byReason[reason].push(entry)
			})

			return Object.keys(byReason)
				.sort()
				.map(function (reason) {
					return { reason: reason, items: byReason[reason] }
				})
		}

		// Build the DTCG import-diagnostics fragment for an upload response:
		// skipped/error paths grouped by reason, plus $deprecated import warnings.
		// Returns an empty (childless) fragment when both arrays are absent/empty,
		// so a CSS upload's plain-text summary is never followed by empty markup.
		function buildDiagnosticsFragment(data) {
			var fragment = document.createDocumentFragment()

			var diagnostics = [].concat(data.skipped || [], data.errors || [])
			var groups =
				typeof TT.groupDiagnosticsByReason === 'function'
					? TT.groupDiagnosticsByReason(diagnostics)
					: groupDiagnosticsByReasonFallback(diagnostics)

			if (groups.length > 0) {
				var list = document.createElement('ul')
				list.className = 'nldesign-diagnostics-list'
				groups.forEach(function (group) {
					var item = document.createElement('li')
					var paths = group.items
						.map(function (entry) {
							return entry.path
						})
						.join(', ')
					item.textContent =
						reasonLabel(group.reason)
						+ ' ('
						+ group.items.length
						+ '): '
						+ paths
					list.appendChild(item)
				})
				fragment.appendChild(list)
			}

			if (data.importWarnings && data.importWarnings.length > 0) {
				var warnList = document.createElement('ul')
				warnList.className = 'nldesign-deprecation-list'
				data.importWarnings.forEach(function (w) {
					var item = document.createElement('li')
					item.textContent = w.path + (w.message ? ': ' + w.message : '')
					warnList.appendChild(item)
				})
				fragment.appendChild(warnList)
				var adoptable = data.importWarnings.filter(function (w) {
					return Boolean(w.token)
				})
				if (adoptable.length > 0) {
					fragment.appendChild(buildAdoptNoticesButton(adoptable))
				}
			}

			return fragment
		}

		/**
		 * "Record as deprecations": keeps an upload's deprecation notices as deprecation records
		 * (openspec/specs/token-deprecations/spec.md, imported notices). Nothing is recorded
		 * without this click.
		 *
		 * @param {Array<object>} notices The notices that name a CSS variable.
		 * @return {HTMLButtonElement} The button.
		 */
		function buildAdoptNoticesButton(notices) {
			var button = document.createElement('button')
			button.type = 'button'
			button.className = 'nldesign-adopt-notices'
			button.textContent = t('thematiq', 'Record as deprecations')
			button.addEventListener('click', function () {
				button.disabled = true
				fetch(
					OC.generateUrl(
						'/apps/thematiq/settings/tokens/deprecations/adopt',
					),
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							requesttoken: OC.requestToken,
						},
						body: JSON.stringify({ notices: notices }),
					},
				)
					.then(function (r) {
						return r.json()
					})
					.then(function (data) {
						var count = (data.recorded || []).length
						notify(
							n(
								'thematiq',
								'Recorded {count} deprecation.',
								'Recorded {count} deprecations.',
								count,
								{ count: count },
							),
						)
						document.dispatchEvent(
							new CustomEvent('thematiq:deprecations-changed'),
						)
					})
					.catch(function () {
						button.disabled = false
						notify(t('thematiq', 'The notices were not recorded.'))
					})
			})
			return button
		}

		/**
		 * The simple brand form (openspec/specs/simple-brand-form/spec.md): the preview derives the
		 * set with window.NldesignBrandForm over the server's rules and defaults, so it shows what is
		 * stored; saving posts to /settings/tokensets/from-colours.
		 */
		function initBrandForm() {
			var form = document.getElementById('nldesign-brand-form')
			var brandForm = window.NldesignBrandForm
			if (form === null || !brandForm) {
				return
			}
			var nameInput = document.getElementById('nldesign-brand-name')
			var primaryInput = document.getElementById('nldesign-brand-primary')
			var backgroundInput = document.getElementById(
				'nldesign-brand-background',
			)
			var logoInput = document.getElementById('nldesign-brand-logo')
			var sample = document.getElementById('nldesign-brand-sample')
			var sampleHover = document.getElementById('nldesign-brand-sample-hover')
			var readout = document.getElementById('nldesign-brand-contrast')
			var resultEl = document.getElementById('nldesign-brand-result')
			var saveBtn = document.getElementById('nldesign-brand-save')
			var inputs = null

			function ratioLine(label, value, minimum) {
				var line = t('thematiq', '{label}: {ratio}:1, needs {min}:1.')
					.replace('{label}', label)
					.replace('{ratio}', value.toFixed(2))
					.replace('{min}', String(minimum))
				if (value < minimum) {
					line +=
						' '
						+ t(
							'thematiq',
							'Warning: below the threshold. You can still save.',
						)
				}
				return line
			}

			function repaint() {
				if (inputs === null) {
					return
				}
				var result = brandForm.derive(
					inputs,
					primaryInput.value,
					backgroundInput.value,
				)
				if (result === null) {
					return
				}
				var d = result.declarations
				sample.style.background = d['--nldesign-color-primary']
				sample.style.color = d['--nldesign-color-primary-text']
				sampleHover.style.background = d['--nldesign-color-primary-hover']
				sampleHover.style.color = d['--nldesign-color-primary-text']
				form.style.setProperty(
					'--brand-form-background',
					d['--nldesign-color-nav-background'],
				)
				readout.textContent =
					ratioLine(
						t('thematiq', 'Text on primary'),
						result.textRatio,
						brandForm.TEXT_MIN,
					)
					+ ' '
					+ ratioLine(
						t('thematiq', 'Primary on background'),
						result.uiRatio,
						brandForm.UI_MIN,
					)
			}

			function showResult(text) {
				resultEl.style.display = 'block'
				resultEl.textContent = text
			}

			// The two colors start as the theme the page is wearing, and follow
			// it when another theme is applied, until the admin picks their own.
			var picked = false
			function followTheme() {
				if (picked === true) {
					return
				}
				var style = getComputedStyle(document.documentElement)
				var primary = style.getPropertyValue('--color-primary').trim()
				var background = style
					.getPropertyValue('--color-main-background')
					.trim()
				// Only what the page actually declares: an empty value would
				// read as black.
				if (primary !== '' && TT.normaliseColorForPicker) {
					primaryInput.value = TT.normaliseColorForPicker(primary)
				}
				if (background !== '' && TT.normaliseColorForPicker) {
					backgroundInput.value = TT.normaliseColorForPicker(background)
				}
				repaint()
			}

			function pick() {
				picked = true
				repaint()
			}

			primaryInput.addEventListener('input', pick)
			backgroundInput.addEventListener('input', pick)
			document.addEventListener('thematiq:theme-applied', followTheme)
			var coloursTab = document.getElementById('nldesign-create-tab-colours')
			if (coloursTab !== null) {
				coloursTab.addEventListener('click', followTheme)
			}
			followTheme()

			var logoBtn = document.getElementById('nldesign-brand-logo-btn')
			var logoName = document.getElementById('nldesign-brand-logo-name')
			if (logoBtn !== null && logoInput !== null) {
				logoBtn.addEventListener('click', function () {
					logoInput.click()
				})
				logoInput.addEventListener('change', function () {
					if (logoName !== null) {
						logoName.textContent =
							logoInput.files && logoInput.files[0]
								? logoInput.files[0].name
								: t('thematiq', 'No file chosen')
					}
				})
			}

			fetch(OC.generateUrl('/apps/thematiq/settings/tokensets/from-colours'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					inputs = data
					repaint()
				})
				.catch(function (err) {
					console.error('Error loading the brand form rules:', err)
				})

			saveBtn.addEventListener('click', function () {
				var name = nameInput.value.trim()
				if (name === '') {
					notify(t('thematiq', 'Enter a token set name first.'))
					nameInput.focus()
					return
				}
				var formData = new FormData()
				formData.append('name', name)
				formData.append('primary', primaryInput.value)
				formData.append('background', backgroundInput.value)
				if (logoInput !== null && logoInput.files && logoInput.files[0]) {
					formData.append('logo', logoInput.files[0])
				}
				fetch(
					OC.generateUrl('/apps/thematiq/settings/tokensets/from-colours'),
					{
						method: 'POST',
						headers: { requesttoken: OC.requestToken },
						body: formData,
					},
				)
					.then(function (r) {
						return r.json().then(function (data) {
							return { status: r.status, data: data }
						})
					})
					.then(function (res) {
						if (res.status >= 400) {
							showResult(
								t('thematiq', 'Not saved:')
									+ ' '
									+ (res.data.error || ''),
							)
							return
						}
						showResult(
							t(
								'thematiq',
								'"{name}" is saved and is now in the dropdown. Select it to apply it.',
							).replace('{name}', name),
						)
						loadCustomTokenSets()
						refreshTokenSetCatalogue().catch(function () {})
					})
					.catch(function (err) {
						console.error('Error creating the house style:', err)
						showResult(t('thematiq', 'Not saved.'))
					})
			})
		}

		function uploadCustomTokenSet(name, file) {
			var resultEl = document.getElementById('nldesign-upload-result')
			var formData = new FormData()
			formData.append('name', name)
			formData.append('file', file)

			fetch(OC.generateUrl('/apps/thematiq/settings/tokensets/upload'), {
				method: 'POST',
				headers: { requesttoken: OC.requestToken },
				body: formData,
			})
				.then(function (r) {
					return r.json().then(function (data) {
						return { status: r.status, data: data }
					})
				})
				.then(function (res) {
					if (resultEl !== null) {
						resultEl.style.display = 'block'
						resultEl.innerHTML = ''
					}
					if (res.status >= 400) {
						if (resultEl !== null) {
							resultEl.appendChild(
								document.createTextNode(
									t('thematiq', 'Upload failed:')
										+ ' '
										+ (res.data.error || ''),
								),
							)
							resultEl.appendChild(buildDiagnosticsFragment(res.data))
						}
						notify(
							t('thematiq', 'Upload failed:')
								+ ' '
								+ (res.data.error || ''),
						)
						return
					}
					var msg = t(
						'thematiq',
						'{imported} tokens imported, {skipped} skipped.',
					)
						.replace('{imported}', res.data.imported)
						.replace('{skipped}', (res.data.skipped || []).length)
					if (res.data.version) {
						msg +=
							' '
							+ t('thematiq', 'Package version: {version}').replace(
								'{version}',
								res.data.version,
							)
					}
					if (res.data.warnings && res.data.warnings.length > 0) {
						msg +=
							' '
							+ t(
								'thematiq',
								'{count} WCAG AA contrast warning(s) — see the apply dialog.',
							).replace('{count}', res.data.warnings.length)
					}
					if (resultEl !== null) {
						resultEl.appendChild(document.createTextNode(msg))
						resultEl.appendChild(buildDiagnosticsFragment(res.data))
					}
					loadCustomTokenSets()
					// The dropdown and tokenSetsData were built from initial state
					// at page load; bring both in line with the catalogue so the
					// new set can be chosen right away. Deliberately NOT selected
					// here — selecting is what opens the apply dialog, and an
					// admin uploading several sets does not want one per upload.
					refreshTokenSetCatalogue()
						.then(function () {
							notify(
								t(
									'thematiq',
									'Token set uploaded — it is now in the dropdown. Select it to apply it.',
								),
							)
							if (tokenSetSelect !== null) {
								tokenSetSelect.focus()
							}
						})
						.catch(function () {
							notify(
								t(
									'thematiq',
									'Token set uploaded. Reload the page to apply it.',
								),
							)
						})
				})
				.catch(function (err) {
					console.error('Error uploading custom token set:', err)
					notify(t('thematiq', 'Upload failed.'))
				})
		}

		/**
		 * The URL of a set's token reference (openspec/specs/token-reference/spec.md).
		 *
		 * @param {string} id The token set id.
		 * @param {string} format `html` or `md`.
		 * @param {boolean} download Whether the browser saves it as a file.
		 * @return {string} The URL.
		 */
		function tokenReferenceUrl(id, format, download) {
			return (
				OC.generateUrl(
					'/apps/thematiq/api/token-sets/'
						+ encodeURIComponent(id)
						+ '/reference',
				)
				+ '?format='
				+ format
				+ (download ? '&download=1' : '')
			)
		}

		/**
		 * Open a CSRF-protected page in a new tab.
		 *
		 * The tab is opened inside the click, so no popup blocker stops it, and
		 * filled once the page has been fetched with the request token.
		 *
		 * @param {string} url     The page.
		 * @param {string} failure What to tell the admin when it fails.
		 *
		 * @return {void}
		 */
		function openWithToken(url, failure) {
			var tab = window.open('', '_blank')
			fetch(url, {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					if (r.ok === false) {
						throw new Error('page failed: ' + r.status)
					}
					return r.blob()
				})
				.then(function (blob) {
					var page = URL.createObjectURL(
						new Blob([blob], { type: 'text/html' }),
					)
					if (tab === null) {
						window.location.assign(page)
						return
					}
					tab.opener = null
					tab.location.href = page
					// Long enough for the tab to have loaded it.
					window.setTimeout(function () {
						URL.revokeObjectURL(page)
					}, 60000)
				})
				.catch(function (err) {
					if (tab !== null) {
						tab.close()
					}
					console.error('Error opening ' + url + ':', err)
					notify(failure)
				})
		}

		/**
		 * Every token reference link on the page, the two by the dropdown and
		 * the two in each custom set row, fetches with the request token.
		 *
		 * The endpoint is CSRF-protected, and following the link carried no
		 * token, so both the page and the download were refused. Delegated on
		 * the settings section, because the custom set rows are rendered after
		 * this runs.
		 *
		 * @spec openspec/specs/token-reference/spec.md
		 */
		function initTokenReferenceClicks() {
			if (settingsEl === null) {
				return
			}
			settingsEl.addEventListener('click', function (e) {
				var link =
					e.target && e.target.closest
						? e.target.closest('a.nldesign-token-reference-link')
						: null
				if (link === null || !link.getAttribute('href')) {
					return
				}
				e.preventDefault()
				var href = link.getAttribute('href')
				var failure = t(
					'thematiq',
					'The token reference could not be loaded.',
				)
				if (href.indexOf('download=1') !== -1) {
					downloadWithToken(href, 'token-reference.md', failure)
					return
				}
				openWithToken(href, failure)
			})
		}

		initTokenReferenceClicks()

		/**
		 * Keep the two reference links next to the dropdown on the selected set.
		 */
		function initTokenReferenceLinks() {
			var view = document.getElementById('nldesign-token-reference-link')
			var save = document.getElementById('nldesign-token-reference-download')
			if (view === null || save === null || tokenSetSelect === null) {
				return
			}
			function update() {
				view.href = tokenReferenceUrl(tokenSetSelect.value, 'html', false)
				save.href = tokenReferenceUrl(tokenSetSelect.value, 'md', true)
			}
			tokenSetSelect.addEventListener('change', update)
			update()
		}

		initTokenReferenceLinks()

		function loadCustomTokenSets() {
			var listEl = document.getElementById('nldesign-custom-set-list')
			if (listEl === null) {
				return
			}
			fetch(OC.generateUrl('/apps/thematiq/settings/tokensets/custom'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					renderCustomSetList(listEl, (data && data.sets) || [])
				})
				.catch(function (err) {
					console.error('Error loading custom token sets:', err)
					listEl.textContent = t(
						'thematiq',
						'Failed to load custom token sets.',
					)
				})
		}

		function renderCustomSetList(listEl, sets) {
			listEl.innerHTML = ''
			if (sets.length === 0) {
				var empty = document.createElement('p')
				empty.className = 'settings-hint'
				empty.textContent = t(
					'thematiq',
					'No custom token sets uploaded yet.',
				)
				listEl.appendChild(empty)
				return
			}

			sets.forEach(function (set) {
				var row = document.createElement('div')
				row.className = 'nldesign-custom-set-row'

				var nameSpan = document.createElement('span')
				nameSpan.className = 'nldesign-custom-set-name'
				nameSpan.textContent = set.name || set.id
				row.appendChild(nameSpan)

				if (set.version) {
					var versionSpan = document.createElement('span')
					versionSpan.className = 'nldesign-custom-set-version'
					versionSpan.textContent = t('thematiq', 'v{version}', {
						version: set.version,
					})
					row.appendChild(versionSpan)
				}

				// Three states, in order of what the admin most needs to know:
				// a set that never defines the vocabulary is broken in a way no
				// contrast ratio can reveal, so "Incomplete set" wins over
				// "Contrast warning".
				var warnings = set.warnings || []
				var incomplete = warnings.filter(function (w) {
					return w && w.kind === 'incomplete'
				})
				var contrast = warnings.filter(function (w) {
					return w && w.kind !== 'incomplete'
				})

				var badge = document.createElement('span')
				badge.className = 'nldesign-badge'
				if (incomplete.length > 0) {
					badge.classList.add('nldesign-badge--warning')
					badge.textContent = t('thematiq', 'Incomplete set')
					badge.setAttribute(
						'title',
						incompleteWarningLines(incomplete[0]).join('\n'),
					)
				} else if (contrast.length > 0) {
					badge.classList.add('nldesign-badge--warning')
					badge.textContent = t('thematiq', 'Contrast warning')
				} else {
					badge.classList.add('nldesign-badge--ok')
					badge.textContent = t('thematiq', 'WCAG AA OK')
				}
				row.appendChild(badge)

				// A set installed from the theme gallery names its source and
				// licence (openspec/specs/theme-gallery/spec.md).
				if (set.provenance && set.provenance.sourceUrl) {
					var provenance = document.createElement('a')
					provenance.className = 'nldesign-custom-set-provenance'
					provenance.href = set.provenance.sourceUrl
					provenance.target = '_blank'
					provenance.rel = 'noopener noreferrer'
					provenance.textContent = t(
						'thematiq',
						'From the gallery, licence {licence}',
						{
							licence: set.provenance.licence || '',
						},
					)
					row.appendChild(provenance)
				}

				var downloadBtn = document.createElement('button')
				downloadBtn.type = 'button'
				downloadBtn.className = 'nldesign-btn nldesign-btn--small'
				downloadBtn.textContent = t('thematiq', 'Download')
				downloadBtn.addEventListener('click', function () {
					downloadWithToken(
						OC.generateUrl(
							'/apps/thematiq/settings/tokensets/custom/'
								+ encodeURIComponent(set.id)
								+ '/export',
						),
						set.id + '.css',
						t('thematiq', 'The token set could not be downloaded.'),
					)
				})
				row.appendChild(downloadBtn)

				// The token reference of this set (openspec/specs/token-reference/spec.md).
				var referenceLink = document.createElement('a')
				referenceLink.className = 'nldesign-token-reference-link'
				referenceLink.href = tokenReferenceUrl(set.id, 'html', false)
				referenceLink.target = '_blank'
				referenceLink.rel = 'noopener noreferrer'
				referenceLink.textContent = t('thematiq', 'Token reference')
				referenceLink.setAttribute(
					'aria-label',
					t('thematiq', 'Token reference of {name}', {
						name: set.name || set.id,
					}),
				)
				row.appendChild(referenceLink)

				var referenceDownload = document.createElement('a')
				referenceDownload.className = 'nldesign-token-reference-link'
				referenceDownload.href = tokenReferenceUrl(set.id, 'md', true)
				referenceDownload.textContent = t('thematiq', 'Download reference')
				referenceDownload.setAttribute(
					'aria-label',
					t('thematiq', 'Download the token reference of {name}', {
						name: set.name || set.id,
					}),
				)
				row.appendChild(referenceDownload)

				var deleteBtn = document.createElement('button')
				deleteBtn.type = 'button'
				deleteBtn.className =
					'nldesign-btn nldesign-btn--small nldesign-btn--danger'
				deleteBtn.textContent = t('thematiq', 'Delete')
				deleteBtn.addEventListener('click', function () {
					deleteCustomSet(set.id, set.name || set.id)
				})
				row.appendChild(deleteBtn)

				listEl.appendChild(row)
			})
		}

		function deleteCustomSet(id, name) {
			OC.dialogs.confirm(
				t(
					'thematiq',
					'Delete the custom token set "{name}"? If it is currently active, the theme will fall back to Nextcloud.',
				).replace('{name}', name),
				t('thematiq', 'Delete custom token set'),
				function (confirmed) {
					if (confirmed !== true) {
						return
					}
					fetch(
						OC.generateUrl(
							'/apps/thematiq/settings/tokensets/custom/'
								+ encodeURIComponent(id),
						),
						{
							method: 'DELETE',
							headers: { requesttoken: OC.requestToken },
						},
					)
						.then(function (r) {
							return r.json()
						})
						.then(function (data) {
							if (data && data.status === 'ok') {
								loadCustomTokenSets()
								var wasOnPage = pageTokenSetId === id
								var wasSelected =
									tokenSetSelect !== null
									&& tokenSetSelect.value === id
								removeTokenSetOption(id)
								// The server reset the active set to `nextcloud` when the
								// deleted one was active; mirror that on this page.
								var restore =
									wasOnPage === true
										? applyLayersFor('nextcloud')
										: Promise.resolve(true)
								restore.then(function () {
									if (wasSelected === true || wasOnPage === true) {
										reflectSelection('nextcloud')
									}

									// Deleting the ACTIVE set made the server undo
									// what that set had pushed into core theming, so
									// the panel further up this page is now stale:
									// re-read it rather than leave the deleted set's
									// colour and logo showing.
									if (wasOnPage === true) {
										refreshCoreTheming().catch(function (error) {
											console.error(
												'Error refreshing Nextcloud theming after a delete:',
												error,
											)
										})
									}

									notify(
										t('thematiq', 'Custom token set deleted.'),
									)
								})
							} else {
								notify(
									t(
										'thematiq',
										'Failed to delete custom token set.',
									),
								)
							}
						})
						.catch(function (err) {
							console.error('Error deleting custom token set:', err)
							notify(
								t('thematiq', 'Failed to delete custom token set.'),
							)
						})
				},
				true,
			)
		}

		/**
		 * The two ways to add a custom token set, as tabs: a click or the arrow,
		 * Home and End keys select a tab and show its panel. Only the selected
		 * tab is in the tab order, the way a WAI-ARIA tablist moves focus.
		 */
		function initCreateTabs() {
			var tablist = document.querySelector('.nldesign-create-tabs')
			if (tablist === null) {
				return
			}
			var tabs = Array.prototype.slice.call(
				tablist.querySelectorAll('.nldesign-create-tab'),
			)

			function select(tab) {
				tabs.forEach(function (candidate) {
					var on = candidate === tab
					candidate.classList.toggle('active', on)
					candidate.setAttribute('aria-selected', on ? 'true' : 'false')
					candidate.tabIndex = on ? 0 : -1
					var panel = document.getElementById(
						candidate.getAttribute('aria-controls'),
					)
					if (panel !== null) {
						panel.hidden = !on
					}
				})
			}

			tabs.forEach(function (tab, index) {
				tab.addEventListener('click', function () {
					select(tab)
				})
				tab.addEventListener('keydown', function (event) {
					var next = null
					if (event.key === 'ArrowRight') {
						next = tabs[(index + 1) % tabs.length]
					} else if (event.key === 'ArrowLeft') {
						next = tabs[(index - 1 + tabs.length) % tabs.length]
					} else if (event.key === 'Home') {
						next = tabs[0]
					} else if (event.key === 'End') {
						next = tabs[tabs.length - 1]
					}
					if (next !== null) {
						event.preventDefault()
						select(next)
						next.focus()
					}
				})
			})
		}

		// Initialise the custom token set panel on page load.
		initCustomTokenSets()
		initCreateTabs()
		initBrandForm()

		/* ==========================================================================
		 * RESET THEME TO NEXTCLOUD
		 *
		 * The way back to stock: the active theme's overrides and the edits
		 * written over the Nextcloud theme are emptied, the stock set becomes
		 * active and core theming is reset (OverridesController::resetToStock()).
		 * The page reloads afterwards, because every panel on it — the editor,
		 * the core-theming fields, the stylesheet run — describes what was reset.
		 * ========================================================================== */

		var resetThemeBtn = document.getElementById('nldesign-reset-theme-btn')
		if (resetThemeBtn !== null) {
			resetThemeBtn.addEventListener('click', function () {
				OC.dialogs.confirm(
					t(
						'thematiq',
						'This fully resets the theme to stock Nextcloud: the overrides of the current theme and every change written over the Nextcloud theme are deleted, and the Nextcloud theming colors and logo return to their defaults. Custom token sets, fonts and custom CSS are kept. This cannot be undone.',
					),
					t('thematiq', 'Reset theme to Nextcloud'),
					function (confirmed) {
						if (confirmed !== true) {
							return
						}

						resetThemeBtn.disabled = true
						fetch(OC.generateUrl('/apps/thematiq/settings/overrides'), {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								requesttoken: OC.requestToken,
							},
							body: JSON.stringify({ reset: true }),
						})
							.then(function (r) {
								return r.json()
							})
							.then(function (data) {
								if (data.status !== 'ok') {
									throw new Error(data.error || 'Reset failed')
								}
								window.location.reload()
							})
							.catch(function (err) {
								resetThemeBtn.disabled = false
								console.error('Error resetting the theme:', err)
								notify(
									t('thematiq', 'The theme could not be reset.'),
								)
							})
					},
					true,
				)
			})
		}

		/* ==========================================================================
		 * CUSTOM FONTS (admin-uploaded, self-hosted webfonts)
		 *
		 * Mirrors the custom token set upload panel above (FormData POST, list
		 * refresh, delete-with-confirm), hardened for binary input: the file
		 * input accepts .woff2 as a UX hint only — the server-side WOFF2 magic
		 * byte check is authoritative and rejects anything else with a 422 the
		 * user sees inline.
		 * ========================================================================== */

		function initCustomFonts() {
			var uploadBtn = document.getElementById('nldesign-font-upload-btn')
			var fileInput = document.getElementById('nldesign-font-input')
			var nameInput = document.getElementById('nldesign-font-name')
			var roleSelect = document.getElementById('nldesign-font-role')
			if (
				uploadBtn === null
				|| fileInput === null
				|| nameInput === null
				|| roleSelect === null
			) {
				return
			}

			uploadBtn.addEventListener('click', function () {
				if (nameInput.value.trim() === '') {
					notify(t('thematiq', 'Enter a font display name first.'))
					nameInput.focus()
					return
				}
				fileInput.click()
			})

			fileInput.addEventListener('change', function (e) {
				var file = e.target.files[0]
				if (!file) {
					return
				}
				uploadFont(nameInput.value.trim(), roleSelect.value, file)
				fileInput.value = ''
			})

			loadFonts()
		}

		function uploadFont(name, role, file) {
			var resultEl = document.getElementById('nldesign-font-upload-result')
			var formData = new FormData()
			formData.append('name', name)
			formData.append('role', role)
			formData.append('font', file)

			fetch(OC.generateUrl('/apps/thematiq/settings/fonts/upload'), {
				method: 'POST',
				headers: { requesttoken: OC.requestToken },
				body: formData,
			})
				.then(function (r) {
					return r.json().then(function (data) {
						return { status: r.status, data: data }
					})
				})
				.then(function (res) {
					if (resultEl !== null) {
						resultEl.style.display = 'block'
					}
					if (res.status >= 400) {
						if (resultEl !== null) {
							resultEl.textContent =
								t('thematiq', 'Upload failed:')
								+ ' '
								+ (res.data.error || '')
						}
						notify(
							t('thematiq', 'Upload failed:')
								+ ' '
								+ (res.data.error || ''),
						)
						return
					}
					if (resultEl !== null) {
						resultEl.textContent = ''
						resultEl.style.display = 'none'
					}
					notify(
						t('thematiq', 'Font uploaded. Reload the page to apply it.'),
					)
					loadFonts()
				})
				.catch(function (err) {
					console.error('Error uploading font:', err)
					notify(t('thematiq', 'Upload failed.'))
				})
		}

		function loadFonts() {
			var listEl = document.getElementById('nldesign-font-list')
			if (listEl === null) {
				return
			}
			fetch(OC.generateUrl('/apps/thematiq/settings/fonts'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					renderFontList(listEl, (data && data.fonts) || [])
				})
				.catch(function (err) {
					console.error('Error loading fonts:', err)
					listEl.textContent = t('thematiq', 'Failed to load fonts.')
				})
		}

		function renderFontList(listEl, fonts) {
			listEl.innerHTML = ''
			if (fonts.length === 0) {
				var empty = document.createElement('p')
				empty.className = 'settings-hint'
				empty.textContent = t('thematiq', 'No fonts uploaded yet.')
				listEl.appendChild(empty)
				return
			}

			fonts.forEach(function (font) {
				var row = document.createElement('div')
				row.className = 'nldesign-custom-set-row'

				var nameSpan = document.createElement('span')
				nameSpan.className = 'nldesign-custom-set-name'
				nameSpan.textContent = font.name || font.id
				row.appendChild(nameSpan)

				var roleBadge = document.createElement('span')
				roleBadge.className = 'nldesign-badge'
				roleBadge.textContent =
					font.role === 'heading'
						? t('thematiq', 'Heading')
						: t('thematiq', 'Body text')
				row.appendChild(roleBadge)

				var sizeSpan = document.createElement('span')
				sizeSpan.className = 'nldesign-badge'
				sizeSpan.textContent =
					Math.max(1, Math.round((font.size || 0) / 1024)) + ' KB'
				row.appendChild(sizeSpan)

				var deleteBtn = document.createElement('button')
				deleteBtn.type = 'button'
				deleteBtn.className =
					'nldesign-btn nldesign-btn--small nldesign-btn--danger'
				deleteBtn.textContent = t('thematiq', 'Delete')
				deleteBtn.addEventListener('click', function () {
					deleteFont(font.id, font.name || font.id)
				})
				row.appendChild(deleteBtn)

				listEl.appendChild(row)
			})
		}

		function deleteFont(id, name) {
			OC.dialogs.confirm(
				t(
					'thematiq',
					'Delete the font "{name}"? Pages using it will fall back to Fira Sans.',
				).replace('{name}', name),
				t('thematiq', 'Delete font'),
				function (confirmed) {
					if (confirmed !== true) {
						return
					}
					fetch(
						OC.generateUrl(
							'/apps/thematiq/settings/fonts/'
								+ encodeURIComponent(id),
						),
						{
							method: 'DELETE',
							headers: { requesttoken: OC.requestToken },
						},
					)
						.then(function (r) {
							return r.json()
						})
						.then(function (data) {
							if (data && data.status === 'ok') {
								notify(
									t(
										'thematiq',
										'Font deleted. Reload the page to refresh the styling.',
									),
								)
								loadFonts()
							} else {
								notify(t('thematiq', 'Failed to delete font.'))
							}
						})
						.catch(function (err) {
							console.error('Error deleting font:', err)
							notify(t('thematiq', 'Failed to delete font.'))
						})
				},
				true,
			)
		}

		// Initialise the custom fonts panel on page load.
		initCustomFonts()

		/* ==========================================================================
		 * THEMING AUDIT LOG
		 *
		 * Read-only panel: fetches the most recent entries on page load and wires
		 * the full-log download button. All entry values are rendered via
		 * textContent (DOM text nodes), never innerHTML — audit entries can carry
		 * admin-supplied names (custom token set names) so must be treated as
		 * untrusted content.
		 * ========================================================================== */

		function initAuditLog() {
			var tableBody = document.getElementById('nldesign-audit-table-body')
			var downloadBtn = document.getElementById('nldesign-audit-download-btn')
			if (tableBody === null) {
				return
			}

			fetch(OC.generateUrl('/apps/thematiq/settings/audit?limit=20'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					renderAuditTable(tableBody, (data && data.entries) || [])
				})
				.catch(function (err) {
					console.error('Error loading audit log:', err)
					renderAuditMessage(
						tableBody,
						t('thematiq', 'Failed to load the audit log.'),
					)
				})

			if (downloadBtn !== null) {
				downloadBtn.addEventListener('click', function () {
					downloadWithToken(
						OC.generateUrl('/apps/thematiq/settings/audit/export'),
						'nldesign-audit.jsonl',
						t('thematiq', 'The audit log could not be downloaded.'),
					)
				})
			}
		}

		function renderAuditMessage(tableBody, message) {
			tableBody.innerHTML = ''
			var row = document.createElement('tr')
			var cell = document.createElement('td')
			cell.colSpan = 7
			cell.className = 'settings-hint'
			cell.textContent = message
			row.appendChild(cell)
			tableBody.appendChild(row)
		}

		/**
		 * The four pure formatters, from js/lib/auditFormat.js.
		 *
		 * Read through a local alias rather than called globally so the panel
		 * degrades the way the rest of this file does when a module is absent:
		 * an entry renders its raw value instead of throwing and taking the
		 * whole table with it.
		 */
		var auditFormat = (typeof window !== 'undefined'
			&& window.ThematiqAuditFormat) || {
			formatAuditChanged: function (entry) {
				return String(entry.changed || '')
			},
			formatAuditTimestamp: function (ts) {
				return String(ts || '')
			},
			formatAuditValue: function (value) {
				return value === null || value === undefined ? '' : String(value)
			},
		}

		function renderAuditTable(tableBody, entries) {
			tableBody.innerHTML = ''

			if (entries.length === 0) {
				renderAuditMessage(
					tableBody,
					t('thematiq', 'No theming changes have been recorded yet.'),
				)
				return
			}

			entries.forEach(function (entry) {
				var row = document.createElement('tr')

				// One fact per cell. The old single Details column held
				// "from X to Y" as one string, which meant the two values could
				// not be lined up down the table — the thing an audit is read
				// for is what changed, and that is a comparison between two
				// columns, not a sentence.
				;[
					[
						'nldesign-audit-ts',
						auditFormat.formatAuditTimestamp(entry.ts),
					],
					['nldesign-audit-actor', entry.actor || ''],
					['nldesign-audit-action', entry.action || ''],
					[
						'nldesign-audit-value',
						auditFormat.formatAuditValue(entry.old),
					],
					[
						'nldesign-audit-value',
						auditFormat.formatAuditValue(entry.new),
					],
					[
						'nldesign-audit-changed',
						auditFormat.formatAuditChanged(entry),
					],
				].forEach(function (cell) {
					var td = document.createElement('td')
					td.className = cell[0]
					// textContent throughout: an entry can carry an
					// admin-supplied custom token set name.
					td.textContent = cell[1]
					row.appendChild(td)
				})

				var restoreCell = document.createElement('td')
				restoreCell.className = 'nldesign-audit-restore-cell'
				if (typeof entry.versionId === 'string' && entry.versionId !== '') {
					var restoreBtn = document.createElement('button')
					restoreBtn.type = 'button'
					restoreBtn.className = 'button nldesign-audit-restore'
					restoreBtn.textContent = t('thematiq', 'Restore')
					restoreBtn.setAttribute(
						'aria-label',
						t('thematiq', 'Restore the configuration from {time}', {
							time: auditFormat.formatAuditTimestamp(entry.ts),
						}),
					)
					restoreBtn.addEventListener('click', function () {
						restoreVersion(entry.versionId, restoreBtn)
					})
					restoreCell.appendChild(restoreBtn)
				}
				row.appendChild(restoreCell)

				tableBody.appendChild(row)
			})
		}

		/**
		 * Describe a version preview as plain text lines for the dialog.
		 *
		 * @param {object} preview The preview from POST /settings/versions/{id}/preview.
		 * @return {string} The description.
		 * @spec openspec/specs/theme-versions/spec.md
		 */
		function describeVersionPreview(preview) {
			var lines = []
			;(preview.changes || []).forEach(function (change) {
				lines.push(
					t('thematiq', '{field}: {from} to {to}', {
						field: change.field,
						from: auditFormat.formatAuditValue(change.from),
						to: auditFormat.formatAuditValue(change.to),
					}),
				)
			})
			var sets = preview.customTokenSets || { add: [], remove: [] }
			;(sets.add || []).forEach(function (id) {
				lines.push(t('thematiq', 'Custom token set added: {id}', { id: id }))
			})
			;(sets.remove || []).forEach(function (id) {
				lines.push(
					t('thematiq', 'Custom token set removed: {id}', { id: id }),
				)
			})
			;(preview.missingFonts || []).forEach(function (font) {
				lines.push(
					t(
						'thematiq',
						'The {role} font {name} is no longer uploaded and stays on the default font.',
						{
							role: font.role,
							name: font.name,
						},
					),
				)
			})
			if (lines.length === 0) {
				lines.push(
					t('thematiq', 'This version matches the current configuration.'),
				)
			}
			return lines.join('\n')
		}

		/**
		 * Preview a version, confirm with the changes listed, then restore.
		 * Nothing is written until the administrator confirms; on cancel the
		 * focus returns to the button that opened the dialog.
		 *
		 * @param {string} versionId The version to restore.
		 * @param {HTMLElement} button The row's restore button.
		 * @spec openspec/specs/theme-versions/spec.md
		 */
		function restoreVersion(versionId, button) {
			var base = OC.generateUrl(
				'/apps/thematiq/settings/versions/' + encodeURIComponent(versionId),
			)
			var post = { method: 'POST', headers: { requesttoken: OC.requestToken } }
			button.disabled = true
			fetch(base + '/preview', post)
				.then(function (r) {
					return r.json()
				})
				.then(function (preview) {
					button.disabled = false
					if (!preview || preview.valid !== true) {
						notify(
							t(
								'thematiq',
								'This version does not validate today and cannot be restored.',
							),
						)
						button.focus()
						return
					}
					OC.dialogs.confirm(
						describeVersionPreview(preview),
						t('thematiq', 'Restore this version?'),
						function (confirmed) {
							if (confirmed !== true) {
								button.focus()
								return
							}
							fetch(base + '/restore', post)
								.then(function (r) {
									return r.json()
								})
								.then(function (result) {
									if (result && result.applied === true) {
										window.location.reload()
										return
									}
									notify(
										t(
											'thematiq',
											'The version was not restored. Nothing was changed.',
										),
									)
									button.focus()
								})
						},
						true,
					)
				})
				.catch(function (err) {
					console.error('Error restoring a version:', err)
					button.disabled = false
					notify(
						t(
							'thematiq',
							'The version was not restored. Nothing was changed.',
						),
					)
					button.focus()
				})
		}

		initAuditLog()

		/**
		 * Point the contrast evidence report links at the export endpoint, one
		 * per format.
		 *
		 * The href stays, so each link still says where it leads, but a click
		 * fetches the file with the request token: the endpoint is
		 * CSRF-protected, and following the link carried no token.
		 *
		 * @spec openspec/specs/compliance-evidence/spec.md
		 */
		function initComplianceReport() {
			var base = OC.generateUrl('/apps/thematiq/settings/compliance-report')
			var links = {
				'nldesign-compliance-report-json': ['json', 'json'],
				'nldesign-compliance-report-markdown': ['markdown', 'md'],
			}
			Object.keys(links).forEach(function (id) {
				var link = document.getElementById(id)
				if (link === null) {
					return
				}
				var url = base + '?format=' + links[id][0]
				link.setAttribute('href', url)
				link.addEventListener('click', function (e) {
					e.preventDefault()
					downloadWithToken(
						url,
						'contrast-report.' + links[id][1],
						t(
							'thematiq',
							'The contrast report could not be downloaded.',
						),
					)
				})
			})
		}

		initComplianceReport()

		/**
		 * Format a UTC ISO time in the administrator's own time zone.
		 *
		 * @param {string} iso The UTC time.
		 * @return {string} The local date and time.
		 * @spec openspec/specs/scheduled-switch/spec.md
		 */
		function formatLocalTime(iso) {
			return new Date(iso).toLocaleString([], {
				dateStyle: 'medium',
				timeStyle: 'short',
			})
		}

		/**
		 * The name of a token set, or its id when the page has none for it.
		 *
		 * @param {string} id The token set id.
		 * @return {string} The name.
		 */
		function tokenSetName(id) {
			var ts = tokenSetsData[id]
			return ts && ts.name ? ts.name : id
		}

		/**
		 * What a running switch means for the dropdown, or '' when none runs.
		 *
		 * @return {string} The sentence.
		 */
		function runningSwitchText() {
			if (runningSwitch === null) {
				return ''
			}
			return t(
				'thematiq',
				'A planned switch to {set} is running until {time}. A token set picked here is put back within five minutes; cancel the switch under Planned switches to change the theme before then.',
				{
					set: tokenSetName(runningSwitch.tokenSet),
					time: formatLocalTime(runningSwitch.until),
				},
				undefined,
				{ escape: false },
			)
		}

		/**
		 * Say beside the dropdown that a running switch will put its set back.
		 *
		 * @return {void}
		 */
		function updateSwitchNote() {
			var note = document.getElementById('nldesign-token-set-switch-note')
			if (note === null) {
				return
			}
			note.textContent = runningSwitchText()
			note.hidden = runningSwitch === null
		}

		/**
		 * The "Planned switches" block: status, cron warning, plan form and
		 * the list with a cancel button per switch. Times are entered and
		 * shown in the browser's time zone and sent as UTC.
		 *
		 * @spec openspec/specs/scheduled-switch/spec.md
		 */
		function initScheduledSwitches() {
			var list = document.getElementById('nldesign-scheduled-list')
			var form = document.getElementById('nldesign-scheduled-form')
			if (list === null || form === null) {
				return
			}
			var url = OC.generateUrl('/apps/thematiq/settings/scheduled-switches')
			var statusEl = document.getElementById('nldesign-scheduled-status')
			var warning = document.getElementById('nldesign-scheduled-cron-warning')
			var zone = document.getElementById('nldesign-scheduled-zone')
			if (zone !== null) {
				zone.textContent = t(
					'thematiq',
					'Times are in your time zone ({zone}).',
					{ zone: Intl.DateTimeFormat().resolvedOptions().timeZone },
				)
			}

			function renderStatus(status) {
				if (!status) {
					return
				}
				runningSwitch =
					status.runningTokenSet && status.activeUntil
						? {
								tokenSet: status.runningTokenSet,
								until: status.activeUntil,
							}
						: null
				updateSwitchNote()
				if (statusEl === null) {
					return
				}
				var lines = []
				if (status.activeUntil) {
					lines.push(
						t(
							'thematiq',
							'{set} is active until {time}, then {previous} comes back.',
							{
								set: status.activeTokenSet,
								time: formatLocalTime(status.activeUntil),
								previous: status.revertTo,
							},
						),
					)
				}
				lines.push(
					status.lastRun
						? t('thematiq', 'The schedule last ran at {time}.', {
								time: formatLocalTime(status.lastRun),
							})
						: t('thematiq', 'The schedule has not run yet.'),
				)
				statusEl.textContent = lines.join(' ')
				if (warning !== null) {
					warning.hidden = status.cronWarning !== true
				}
			}

			function describe(entry) {
				var text = entry.endAt
					? t('thematiq', '{set} from {start} to {end}', {
							set: entry.tokenSet,
							start: formatLocalTime(entry.startAt),
							end: formatLocalTime(entry.endAt),
						})
					: t('thematiq', '{set} from {start}, no end', {
							set: entry.tokenSet,
							start: formatLocalTime(entry.startAt),
						})
				if (entry.status === 'running') {
					return text + ' (' + t('thematiq', 'running') + ')'
				}
				if (entry.status === 'failed') {
					return (
						text
						+ '. '
						+ t('thematiq', 'Failed: {reason}', {
							reason: entry.failureReason || '',
						})
					)
				}
				return text
			}

			function renderList(switches) {
				list.innerHTML = ''
				if (!switches || switches.length === 0) {
					var empty = document.createElement('li')
					empty.className = 'settings-hint'
					empty.textContent = t('thematiq', 'No switches are planned.')
					list.appendChild(empty)
					return
				}
				switches.forEach(function (entry) {
					var item = document.createElement('li')
					item.className = 'nldesign-scheduled-item'
					if (entry.status === 'failed') {
						item.classList.add('nldesign-scheduled-item--failed')
					}
					var label = document.createElement('span')
					label.textContent = describe(entry)
					item.appendChild(label)
					var cancel = document.createElement('button')
					cancel.type = 'button'
					cancel.className = 'button nldesign-scheduled-cancel'
					cancel.textContent = t('thematiq', 'Cancel')
					cancel.setAttribute(
						'aria-label',
						t('thematiq', 'Cancel the switch to {set}', {
							set: entry.tokenSet,
						}),
					)
					cancel.addEventListener('click', function () {
						cancelSwitch(entry.id, cancel)
					})
					item.appendChild(cancel)
					list.appendChild(item)
				})
			}

			function load() {
				return fetch(url, { headers: { requesttoken: OC.requestToken } })
					.then(function (r) {
						return r.json()
					})
					.then(function (data) {
						renderStatus(data.status)
						renderList(data.switches)
					})
					.catch(function (err) {
						console.error('Error loading planned switches:', err)
					})
			}

			function cancelSwitch(id, button) {
				button.disabled = true
				fetch(url + '/' + encodeURIComponent(id), {
					method: 'DELETE',
					headers: { requesttoken: OC.requestToken },
				})
					.then(function (r) {
						return r.json().then(function (body) {
							if (!r.ok) {
								notify(
									body.error
										|| t(
											'thematiq',
											'The switch was not cancelled.',
										),
								)
							}
							return load()
						})
					})
					.catch(function (err) {
						console.error('Error cancelling a planned switch:', err)
						button.disabled = false
						notify(t('thematiq', 'The switch was not cancelled.'))
					})
			}

			function toUtc(value) {
				return value ? new Date(value).toISOString() : ''
			}

			form.addEventListener('submit', function (event) {
				event.preventDefault()
				var start = document.getElementById('nldesign-scheduled-start').value
				if (!start) {
					notify(t('thematiq', 'Enter the start as a date and a time.'))
					return
				}
				var params = {
					tokenSet: document.getElementById('nldesign-scheduled-set')
						.value,
					startAt: toUtc(start),
					endAt: toUtc(
						document.getElementById('nldesign-scheduled-end').value,
					),
				}
				fetch(url, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						requesttoken: OC.requestToken,
					},
					body: new URLSearchParams(params).toString(),
				})
					.then(function (r) {
						return r.json().then(function (body) {
							if (!r.ok) {
								notify(
									body.error
										|| t(
											'thematiq',
											'The switch was not planned.',
										),
								)
								return undefined
							}
							form.reset()
							return load()
						})
					})
					.catch(function (err) {
						console.error('Error planning a switch:', err)
						notify(t('thematiq', 'The switch was not planned.'))
					})
			})

			load()
		}

		initScheduledSwitches()

		/* ==========================================================================
		 * CONFIGURATION BUNDLE — complete-config OTAP promotion download/upload
		 * (config-portability spec). Distinct from the token-editor overrides
		 * download/upload above: this bundle covers the token set, both toggles,
		 * per-app exclusions, overrides CSS, custom token sets, the email footer,
		 * and the upstream-freshness toggle in one JSON file.
		 * ========================================================================== */

		function downloadConfigBundle() {
			downloadWithToken(
				OC.generateUrl('/apps/thematiq/settings/config/export'),
				'thematiq-config.json',
				t('thematiq', 'The configuration could not be downloaded.'),
			)
		}

		function showConfigBundleResult(message) {
			var resultEl = document.getElementById('nldesign-config-bundle-result')
			if (resultEl === null) {
				return
			}
			resultEl.textContent = message
			resultEl.style.display = 'block'
		}

		function uploadConfigBundle(file) {
			var formData = new FormData()
			formData.append('file', file)

			fetch(OC.generateUrl('/apps/thematiq/settings/config/import'), {
				method: 'POST',
				headers: { requesttoken: OC.requestToken },
				body: formData,
			})
				.then(function (r) {
					return r.json().then(function (data) {
						return { status: r.status, data: data }
					})
				})
				.then(function (result) {
					if (result.status === 200 && result.data.applied === true) {
						showConfigBundleResult(
							t(
								'thematiq',
								'Configuration imported successfully. Reloading…',
							),
						)
						notify(t('thematiq', 'Configuration imported successfully.'))
						window.setTimeout(function () {
							window.location.reload()
						}, 1200)
						return
					}

					var errors = (result.data && result.data.errors) || []
					var lines = errors.map(function (e) {
						return (
							'[' + (e.section || 'unknown') + '] ' + (e.message || '')
						)
					})
					showConfigBundleResult(
						t('thematiq', 'Import failed — nothing was applied:')
							+ ' '
							+ lines.join('; '),
					)
					notify(t('thematiq', 'Configuration import failed.'))
				})
				.catch(function (err) {
					console.error('Error importing configuration bundle:', err)
					showConfigBundleResult(t('thematiq', 'Import failed.'))
					notify(t('thematiq', 'Configuration import failed.'))
				})
		}

		function initConfigBundle() {
			var downloadBtn = document.getElementById(
				'nldesign-config-bundle-download-btn',
			)
			var uploadBtn = document.getElementById(
				'nldesign-config-bundle-upload-btn',
			)
			var fileInput = document.getElementById('nldesign-config-bundle-input')
			if (downloadBtn === null || uploadBtn === null || fileInput === null) {
				return
			}

			downloadBtn.addEventListener('click', downloadConfigBundle)

			uploadBtn.addEventListener('click', function () {
				fileInput.click()
			})

			fileInput.addEventListener('change', function (e) {
				var file = e.target.files[0]
				if (file) {
					uploadConfigBundle(file)
				}
				fileInput.value = ''
			})
		}

		initConfigBundle()

		/* ==========================================================================
		 * EMAIL TEMPLATE THEMING — mail_template_class toggle + compliance footer
		 * ========================================================================== */

		// Re-render the panel from a { state, footer } payload (shared by the
		// initial GET and every subsequent POST response).
		function renderEmailTheming(state, footer) {
			var root = document.getElementById('nldesign-email-theming')
			var checkbox = document.getElementById('nldesign-email-theming-enabled')
			var occHint = document.getElementById('nldesign-email-occ-hint')
			if (root === null || checkbox === null) {
				return
			}

			if (state) {
				root.setAttribute('data-state', state.state || 'disabled')
				root.setAttribute(
					'data-config-read-only',
					state.configReadOnly ? '1' : '0',
				)
				root.setAttribute('data-foreign-class', state.foreignClass || '')
				checkbox.checked = state.state === 'enabled'
				checkbox.disabled = state.state === 'foreign'
			}

			if (footer) {
				var orgInput = document.getElementById(
					'nldesign-email-footer-org-name',
				)
				var a11yInput = document.getElementById(
					'nldesign-email-footer-accessibility-url',
				)
				var privacyInput = document.getElementById(
					'nldesign-email-footer-privacy-url',
				)
				if (orgInput !== null) {
					orgInput.value = footer.orgName || ''
				}
				if (a11yInput !== null) {
					a11yInput.value = footer.accessibilityUrl || ''
				}
				if (privacyInput !== null) {
					privacyInput.value = footer.privacyUrl || ''
				}
			}

			if (occHint !== null) {
				occHint.style.display = 'none'
			}
		}

		function initEmailTheming() {
			var root = document.getElementById('nldesign-email-theming')
			var saveBtn = document.getElementById('nldesign-email-theming-save')
			if (root === null || saveBtn === null) {
				return
			}

			fetch(OC.generateUrl('/apps/thematiq/settings/email-theming'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					renderEmailTheming(data && data.state, data && data.footer)
				})
				.catch(function (err) {
					console.error('Error loading email theming:', err)
				})

			saveBtn.addEventListener('click', saveEmailTheming)
		}

		function saveEmailTheming() {
			var checkbox = document.getElementById('nldesign-email-theming-enabled')
			var orgInput = document.getElementById('nldesign-email-footer-org-name')
			var a11yInput = document.getElementById(
				'nldesign-email-footer-accessibility-url',
			)
			var privacyInput = document.getElementById(
				'nldesign-email-footer-privacy-url',
			)
			var feedback = document.getElementById('nldesign-email-theming-feedback')
			var occHint = document.getElementById('nldesign-email-occ-hint')
			var foreignNote = document.querySelector('.nldesign-email-foreign-note')
			if (checkbox === null) {
				return
			}

			var payload = {
				enabled: checkbox.checked,
				orgName: orgInput !== null ? orgInput.value : '',
				accessibilityUrl: a11yInput !== null ? a11yInput.value : '',
				privacyUrl: privacyInput !== null ? privacyInput.value : '',
			}

			fetch(OC.generateUrl('/apps/thematiq/settings/email-theming'), {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify(payload),
			})
				.then(function (r) {
					return r.json().then(function (data) {
						return { ok: r.ok, data: data }
					})
				})
				.then(function (result) {
					var data = result.data

					if (result.ok === true && data && data.status === 'ok') {
						renderEmailTheming(data.state, data.footer)
						if (feedback !== null) {
							feedback.textContent = t(
								'thematiq',
								'Email template settings saved.',
							)
						}
						notify(t('thematiq', 'Email template settings saved.'))
						return
					}

					if (data && data.error === 'config_read_only') {
						checkbox.checked = false
						renderEmailTheming(null, data.footer)
						if (occHint !== null) {
							occHint.style.display = ''
							var enableCode = document.getElementById(
								'nldesign-email-occ-enable',
							)
							var disableCode = document.getElementById(
								'nldesign-email-occ-disable',
							)
							if (enableCode !== null && data.occEnable) {
								enableCode.textContent = data.occEnable
							}
							if (disableCode !== null && data.occDisable) {
								disableCode.textContent = data.occDisable
							}
						}
						notify(
							t(
								'thematiq',
								'config.php is read-only; run the shown occ command manually.',
							),
						)
						return
					}

					if (data && data.error === 'foreign_mail_template_class') {
						checkbox.checked = false
						checkbox.disabled = true
						renderEmailTheming(null, data.footer)
						if (foreignNote !== null) {
							foreignNote.textContent = t(
								'thematiq',
								'A different mail template class is already configured ({class}); nldesign will not overwrite it.',
								{ class: data.class },
							)
						}
						notify(
							t(
								'thematiq',
								'A different mail template class is already configured.',
							),
						)
						return
					}

					if (data && data.error === 'invalid_footer') {
						notify(
							t(
								'thematiq',
								'Invalid footer URL — use an http:// or https:// address.',
							),
						)
						return
					}

					notify(t('thematiq', 'Failed to save email template settings.'))
				})
				.catch(function (err) {
					console.error('Error saving email theming:', err)
					notify(t('thematiq', 'Failed to save email template settings.'))
				})
		}

		// Initialise the email theming panel on page load.
		initEmailTheming()

		/* ==========================================================================
		 * UPSTREAM TOKEN FRESHNESS — opt-in daily check against upstream
		 * nl-design-system/themes (openspec/specs/upstream-freshness/spec.md).
		 * No apply control here: informational only.
		 * ========================================================================== */

		// Format an epoch-seconds timestamp (as returned by the status endpoint)
		// for the "last checked" hint. Falls back to the raw value if Intl/Date
		// parsing is unavailable.
		function formatCheckedAt(value) {
			if (!value) {
				return ''
			}
			var ms = /^[0-9]+$/.test(String(value))
				? parseInt(value, 10) * 1000
				: Date.parse(value)
			if (isNaN(ms)) {
				return String(value)
			}
			try {
				return new Date(ms).toLocaleString()
			} catch (e) {
				return String(value)
			}
		}

		// Build the human-facing label for one notice: the specific-set message
		// when a setId was attributed, or a generic fallback when attribution
		// failed and only a head SHA is known.
		function upstreamNoticeLabel(notice) {
			var version =
				notice.upstreamVersion
				|| (notice.headSha ? notice.headSha.slice(0, 7) : '')
			if (notice.setId && notice.setId !== '__generic__') {
				var ts = tokenSetsData[notice.setId]
				var name = ts ? ts.name : notice.setId
				return t(
					'thematiq',
					'Token set {name} has upstream update {version} — review & apply',
					{ name: name, version: version },
				)
			}
			return t(
				'thematiq',
				'Upstream token sets have updates ({version}) — review & apply',
				{ version: version },
			)
		}

		function renderUpstreamFreshness(data) {
			var toggle = document.getElementById(
				'nldesign-upstream-freshness-toggle',
			)
			var lastCheckedEl = document.getElementById(
				'nldesign-upstream-freshness-lastchecked',
			)
			var noticesEl = document.getElementById(
				'nldesign-upstream-freshness-notices',
			)
			if (toggle === null || noticesEl === null) {
				return
			}

			toggle.checked = data.enabled === true

			if (lastCheckedEl !== null) {
				lastCheckedEl.textContent = data.lastChecked
					? t('thematiq', 'Last checked: {when}', {
							when: formatCheckedAt(data.lastChecked),
						})
					: t('thematiq', 'Not checked yet.')
			}

			noticesEl.innerHTML = ''
			;(data.notices || []).forEach(function (notice) {
				var row = document.createElement('div')
				row.className = 'nldesign-upstream-notice'

				var label = document.createElement('span')
				label.textContent = upstreamNoticeLabel(notice)
				row.appendChild(label)

				var dismissBtn = document.createElement('button')
				dismissBtn.type = 'button'
				dismissBtn.className = 'nldesign-btn nldesign-btn--small'
				dismissBtn.textContent = t('thematiq', 'Dismiss')
				dismissBtn.addEventListener('click', function () {
					dismissUpstreamNotice(
						notice.setId,
						notice.upstreamVersion || notice.headSha,
					)
				})
				row.appendChild(dismissBtn)

				noticesEl.appendChild(row)
			})
		}

		function loadUpstreamFreshness() {
			fetch(OC.generateUrl('/apps/thematiq/settings/upstream-freshness'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					return r.json()
				})
				.then(renderUpstreamFreshness)
				.catch(function (err) {
					console.error('Error loading upstream freshness status:', err)
				})
		}

		function dismissUpstreamNotice(setId, version) {
			fetch(
				OC.generateUrl('/apps/thematiq/settings/upstream-freshness/dismiss'),
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify({ setId: setId, version: version || '' }),
				},
			)
				.then(function (r) {
					return r.json()
				})
				.then(function () {
					loadUpstreamFreshness()
				})
				.catch(function (err) {
					console.error('Error dismissing upstream freshness notice:', err)
				})
		}

		function initUpstreamFreshness() {
			var toggle = document.getElementById(
				'nldesign-upstream-freshness-toggle',
			)
			if (toggle === null) {
				return
			}

			toggle.addEventListener('change', function () {
				var enabled = toggle.checked
				fetch(OC.generateUrl('/apps/thematiq/settings/upstream-freshness'), {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify({ enabled: enabled }),
				})
					.then(function (r) {
						return r.json()
					})
					.then(function () {
						notify(
							enabled
								? t(
										'thematiq',
										'Upstream token update checks enabled.',
									)
								: t(
										'thematiq',
										'Upstream token update checks disabled.',
									),
						)
						loadUpstreamFreshness()
					})
					.catch(function (err) {
						console.error(
							'Error saving upstream freshness setting:',
							err,
						)
						toggle.checked = !enabled
					})
			})

			loadUpstreamFreshness()
		}

		// Initialise the upstream freshness panel on page load.
		initUpstreamFreshness()

		/**
		 * Theme gallery (openspec/specs/theme-gallery/spec.md): an opt-in
		 * index of house styles. The toggle label names the index host, the
		 * list shows each entry with its swatches, licence, source and
		 * contrast result, and install or update runs the upload path on the
		 * server. Nothing is fetched from the index while the toggle is off;
		 * the server makes no request then either.
		 */
		function initGallery() {
			var toggle = document.getElementById('nldesign-gallery-toggle')
			var label = document.getElementById('nldesign-gallery-toggle-label')
			var statusEl = document.getElementById('nldesign-gallery-status')
			var list = document.getElementById('nldesign-gallery-list')
			if (toggle === null || list === null) {
				return
			}
			var url = OC.generateUrl('/apps/thematiq/settings/gallery')

			function setStatus(text) {
				if (statusEl !== null) {
					statusEl.textContent = text
				}
			}

			function contrastText(contrast) {
				if (
					contrast
					&& typeof contrast === 'object'
					&& contrast.fail !== undefined
				) {
					return Number(contrast.fail) === 0
						? t('thematiq', 'Contrast: all checks pass')
						: t('thematiq', 'Contrast checks failed: {count}', {
								count: contrast.fail,
							})
				}
				if (typeof contrast === 'string' && contrast !== '') {
					return t('thematiq', 'Contrast: {result}', { result: contrast })
				}
				return t('thematiq', 'Contrast: not checked')
			}

			function renderEntry(entry) {
				var item = document.createElement('li')
				item.className = 'nldesign-gallery-entry'
				item.setAttribute('data-gallery-id', entry.id)

				var swatches = document.createElement('span')
				swatches.className = 'nldesign-gallery-swatches'
				swatches.setAttribute('aria-hidden', 'true')
				;['primary', 'background', 'text'].forEach(function (key) {
					var swatch = document.createElement('span')
					swatch.className = 'nldesign-gallery-swatch'
					swatch.style.backgroundColor = entry.swatches[key]
					swatches.appendChild(swatch)
				})
				item.appendChild(swatches)

				var text = document.createElement('span')
				text.className = 'nldesign-gallery-text'
				var name = document.createElement('strong')
				name.textContent = entry.name
				text.appendChild(name)
				var meta = document.createElement('span')
				meta.className = 'nldesign-gallery-meta'
				meta.textContent = t(
					'thematiq',
					'{organisation}, licence {licence}. {contrast}.',
					{
						organisation: entry.organisation,
						licence: entry.licence,
						contrast: contrastText(entry.contrast),
					},
				)
				text.appendChild(meta)
				var source = document.createElement('a')
				source.href = entry.sourceUrl
				source.target = '_blank'
				source.rel = 'noopener noreferrer'
				source.textContent = t('thematiq', 'Source of {name}', {
					name: entry.name,
				})
				text.appendChild(source)
				item.appendChild(text)

				if (entry.installed && !entry.updateAvailable) {
					var done = document.createElement('span')
					done.className = 'nldesign-badge'
					done.textContent = t('thematiq', 'Installed')
					item.appendChild(done)
					return item
				}

				var button = document.createElement('button')
				button.type = 'button'
				button.className = 'nldesign-btn nldesign-btn--small'
				if (entry.updateAvailable) {
					var badge = document.createElement('span')
					badge.className = 'nldesign-badge nldesign-badge--warning'
					badge.textContent = t('thematiq', 'Update available')
					item.appendChild(badge)
					button.textContent = t('thematiq', 'Update')
					button.setAttribute(
						'aria-label',
						t('thematiq', 'Update {name}', { name: entry.name }),
					)
				} else {
					button.textContent = t('thematiq', 'Install')
					button.setAttribute(
						'aria-label',
						t('thematiq', 'Install {name}', { name: entry.name }),
					)
				}
				button.addEventListener('click', function () {
					install(entry, button)
				})
				item.appendChild(button)
				return item
			}

			function render(data) {
				toggle.checked = data.enabled === true
				if (label !== null && data.host) {
					label.textContent = t(
						'thematiq',
						'Show the theme gallery (contacts {host})',
						{ host: data.host },
					)
				}
				list.innerHTML = ''
				if (data.enabled !== true) {
					setStatus('')
					return
				}
				if (data.reachable === false) {
					setStatus(
						t(
							'thematiq',
							'The gallery could not be reached. You can still upload a token set file under Custom token sets.',
						),
					)
					return
				}
				var entries = data.entries || []
				setStatus(
					entries.length === 0
						? t('thematiq', 'The gallery lists no house styles yet.')
						: '',
				)
				entries.forEach(function (entry) {
					list.appendChild(renderEntry(entry))
				})
			}

			function load() {
				return fetch(url, { headers: { requesttoken: OC.requestToken } })
					.then(function (r) {
						return r.json()
					})
					.then(render)
					.catch(function (err) {
						console.error('Error loading the theme gallery:', err)
					})
			}

			function install(entry, button) {
				button.disabled = true
				setStatus(t('thematiq', 'Installing {name}…', { name: entry.name }))
				fetch(
					OC.generateUrl(
						'/apps/thematiq/settings/gallery/'
							+ encodeURIComponent(entry.id)
							+ '/install',
					),
					{ method: 'POST', headers: { requesttoken: OC.requestToken } },
				)
					.then(function (r) {
						return r.json().then(function (body) {
							return { ok: r.ok, body: body }
						})
					})
					.then(function (result) {
						if (!result.ok) {
							button.disabled = false
							setStatus(
								result.body.error
									|| t('thematiq', '{name} was not installed.', {
										name: entry.name,
									}),
							)
							return
						}
						notify(
							t(
								'thematiq',
								'{name} is installed. Choose it in the Design token set list.',
								{ name: entry.name },
							),
						)
						refreshTokenSetCatalogue()
						loadCustomTokenSets()
						load()
					})
					.catch(function (err) {
						console.error('Error installing a gallery entry:', err)
						button.disabled = false
						setStatus(
							t('thematiq', '{name} was not installed.', {
								name: entry.name,
							}),
						)
					})
			}

			toggle.addEventListener('change', function () {
				var enabled = toggle.checked
				fetch(url, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify({ enabled: enabled }),
				})
					.then(function (r) {
						return r.json()
					})
					.then(render)
					.catch(function (err) {
						console.error('Error saving the gallery setting:', err)
						toggle.checked = !enabled
					})
			})

			load()
		}

		initGallery()

		/**
		 * Freeform custom CSS panel.
		 *
		 * The server is the authority on what is acceptable: this only relays the
		 * payload and renders whatever validation errors come back. Rejections
		 * arrive as HTTP 422 with an `errors` array and nothing is written, so the
		 * textarea keeps the admin's text for correction rather than clearing it.
		 */
		function initCustomCss() {
			var textarea = document.getElementById('nldesign-custom-css-input')
			var toggle = document.getElementById('nldesign-custom-css-enabled')
			var saveBtn = document.getElementById('nldesign-custom-css-save')
			var feedback = document.getElementById('nldesign-custom-css-feedback')

			if (!textarea || !toggle || !saveBtn) {
				return
			}

			var url = OC.generateUrl('/apps/thematiq/settings/custom-css')

			function setFeedback(message, isError) {
				if (!feedback) {
					return
				}
				feedback.textContent = message
				feedback.style.color = isError ? 'var(--color-error, #d80000)' : ''
			}

			fetch(url, { headers: { requesttoken: OC.requestToken } })
				.then(function (res) {
					return res.json()
				})
				.then(function (data) {
					textarea.value = data.css || ''
					toggle.checked = !!data.enabled
				})
				.catch(function (err) {
					console.error('Error loading custom CSS:', err)
				})

			saveBtn.addEventListener('click', function () {
				setFeedback('', false)
				saveBtn.disabled = true

				fetch(url, {
					method: 'POST',
					headers: {
						requesttoken: OC.requestToken,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify({
						css: textarea.value,
						enabled: toggle.checked,
					}),
				})
					.then(function (res) {
						return res.json().then(function (body) {
							return { status: res.status, body: body }
						})
					})
					.then(function (result) {
						if (result.status === 422 && result.body.errors) {
							// Fail-closed: nothing was written. Show every reason.
							setFeedback(result.body.errors.join(' '), true)
							return
						}
						if (result.status !== 200) {
							setFeedback(
								result.body.error || 'Could not save custom CSS.',
								true,
							)
							return
						}
						setFeedback(
							t('thematiq', 'Saved. Reload to see the change.'),
							false,
						)
					})
					.catch(function (err) {
						console.error('Error saving custom CSS:', err)
						setFeedback('Could not save custom CSS.', true)
					})
					.then(function () {
						saveBtn.disabled = false
					})
			})
		}

		initCustomCss()
	}
})()
