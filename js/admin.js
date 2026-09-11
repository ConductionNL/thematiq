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
		var tokenSetSelect = document.getElementById('nldesign-token-set-select')
		var hideSloganCheckbox = document.getElementById('nldesign-hide-slogan')
		var previewRoot = document.getElementById('nldesign-preview')

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
						function () {
							pageTokenSetId = tokenSetId
							pageManifest = manifests[1]
							return true
						},
					)
				},
			)
		}

		/**
		 * custom-overrides.css changed under the same URL (the apply dialog or
		 * the token editor wrote it): make the browser fetch it again.
		 */
		function refreshCustomOverridesLink() {
			if (LayerSwap === null) {
				return
			}
			LayerSwap.refreshStylesheets(
				document,
				'link[rel="stylesheet"][href*="/thematiq/css/custom-overrides.css"]',
			)
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
		function updatePreview(tokenSet) {
			if (!previewRoot) {
				return
			}
			var colors = getPreviewColors(tokenSet)
			var s = previewRoot.style
			s.setProperty('--prev-primary', colors.primary)
			s.setProperty(
				'--prev-primary-text',
				colors.primaryText || readVar('--color-primary-text', '#ffffff'),
			)
			s.setProperty(
				'--prev-surface',
				readVar('--color-main-background', '#ffffff'),
			)
			s.setProperty('--prev-bg', readVar('--color-background-dark', '#f2f4f7'))
			s.setProperty('--prev-text', readVar('--color-main-text', '#1b2733'))
			s.setProperty(
				'--prev-muted',
				readVar('--color-text-maxcontrast', '#6b7785'),
			)
			s.setProperty('--prev-border', readVar('--color-border', '#e3e9f0'))
			s.setProperty('--prev-warning', readVar('--color-warning', '#c79a00'))
			s.setProperty('--prev-error', readVar('--color-error', '#c0392b'))
			s.setProperty(
				'--prev-info',
				readVar(
					'--color-info',
					readVar('--color-primary-element', colors.primary),
				),
			)
			s.setProperty('--prev-radius', readVar('--border-radius-element', '8px'))
			// The rounded container Nextcloud clips the navigation and the app
			// content into, and the pill radius of a navigation entry.
			s.setProperty(
				'--prev-radius-container',
				readVar(
					'--body-container-radius',
					readVar('--border-radius-large', '12px'),
				),
			)
			s.setProperty(
				'--prev-radius-pill',
				readVar('--border-radius-pill', '999px'),
			)
			// The page background BEHIND the content container — visible in the
			// gap around it, which is where a themed instance shows its plain
			// colour or background image.
			s.setProperty(
				'--prev-plain',
				readVar(
					'--color-background-plain',
					readVar('--color-background-dark', '#f2f4f7'),
				),
			)
			// The header is its own role: a set may paint it differently from the
			// primary (Cunningham and Amsterdam both paint it white, over a blue
			// primary). Fall back to the primary only when the page carries no
			// header token.
			s.setProperty(
				'--prev-header-bg',
				readVar('--nldesign-color-header-background', colors.primary),
			)
			s.setProperty(
				'--prev-header-text',
				readVar(
					'--nldesign-color-header-text',
					colors.primaryText || readVar('--color-primary-text', '#ffffff'),
				),
			)
			s.setProperty(
				'--prev-header-border',
				readVar('--nldesign-header-border-bottom', '0'),
			)
			s.setProperty('--prev-login-bg', colors.primary)
		}

		// App / Login preview switch.
		if (previewRoot) {
			previewRoot
				.querySelectorAll('.nldesign-preview-switch-btn')
				.forEach(function (btn) {
					btn.addEventListener('click', function () {
						var view = btn.getAttribute('data-view')
						previewRoot
							.querySelectorAll('.nldesign-preview-switch-btn')
							.forEach(function (b) {
								var on = b === btn
								b.classList.toggle('active', on)
								b.setAttribute(
									'aria-selected',
									on ? 'true' : 'false',
								)
							})
						previewRoot
							.querySelectorAll('.nldesign-preview-stage')
							.forEach(function (stage) {
								stage.hidden =
									stage.getAttribute('data-view') !== view
							})
					})
				})
		}

		// Design system display names (inline fallback for designSystemLabel()).
		var designSystemNames = {
			none: 'Stock Nextcloud',
			nldesign: 'NL Design System',
		}

		// Update the design system badge for the selected token set
		function updateDesignSystemBadge(tokenSetId) {
			var badge = document.getElementById('nldesign-design-system-badge')
			if (!badge) return

			var option = tokenSetSelect
				? tokenSetSelect.querySelector('option[value="' + tokenSetId + '"]')
				: null
			var dsId = option
				? option.getAttribute('data-design-system') || 'nldesign'
				: 'nldesign'
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
						'Primary colour {css} in the token CSS disagrees with {manifest} in the token set manifest.',
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
											'nldesign',
											'Theme published instance-wide. Reload the page to see changes.',
										)
									: t(
											'nldesign',
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
		function computeThemingPlan(tokenSetData, currentTheming) {
			var none = { mode: 'none', diffs: [], payload: null }
			if (!tokenSetData || !currentTheming) {
				return none
			}

			var defaultLabel = t('thematiq', 'Nextcloud default')

			if ((tokenSetData.design_system || 'nldesign') === 'none') {
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

			if (diffs.length === 0) {
				return none
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
						"Stock Nextcloud means Nextcloud's own colours and logo. Everything Thematiq synced into Nextcloud theming is undone; the token set itself is already applied.",
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
							'nldesign',
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
						'nldesign',
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
						'nldesign',
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
							|| diff.key === 'background'
						) {
							payload[diff.key] = proposed[diff.key]
						}
					})

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
										'nldesign',
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

		// Escape HTML to prevent XSS
		function escapeHtml(text) {
			var div = document.createElement('div')
			div.textContent = text
			return div.innerHTML
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
												'nldesign',
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
												'nldesign',
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
		// Registry from server: token name → { tab, type, label }
		var tokenRegistry = {}
		// Tab labels from server: tab id → display label
		var tokenTabLabels = {}

		/**
		 * Initialise and mount the token editor panel into #nldesign-token-editor.
		 */
		function initTokenEditor() {
			var container = document.getElementById('nldesign-token-editor')
			if (container === null) {
				return
			}

			fetch(OC.generateUrl('/apps/thematiq/settings/overrides'), {
				headers: { requesttoken: OC.requestToken },
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					tokenRegistry = data.registry || {}
					tokenTabLabels = data.tabs || {}
					var overrides = data.overrides || {}

					// Read resolved values from the live CSS stack.
					var rootStyle = getComputedStyle(document.documentElement)
					Object.keys(tokenRegistry).forEach(function (name) {
						var resolved = rootStyle.getPropertyValue(name).trim()
						var overridden =
							overrides[name] !== undefined ? overrides[name] : null
						tokenEditorState[name] = {
							resolved: resolved,
							custom: overridden,
							current: overridden !== null ? overridden : resolved,
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
				+ '<button class="nldesign-btn nldesign-btn--small" id="nldesign-export-btn">'
				+ escapeHtml(t('thematiq', 'Download'))
				+ '</button>'
				+ '<label class="nldesign-btn nldesign-btn--small" style="cursor:pointer">'
				+ escapeHtml(t('thematiq', 'Upload'))
				+ '<input type="file" id="nldesign-import-input" accept=".css" style="display:none">'
				+ '</label>'
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

			document
				.getElementById('nldesign-save-btn')
				.addEventListener('click', saveOverrides)
			document
				.getElementById('nldesign-export-btn')
				.addEventListener('click', exportOverrides)
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
				t('thematiq', 'Colour picker for {label}', {
					label: meta.label || name,
				}),
			)

			var inputHtml = ''
			if (meta.type === 'color') {
				var pickerVal = normaliseColorForPicker(displayVal)
				inputHtml =
					'<div class="nldesign-color-input-wrap">'
					+ '<input type="color" class="nldesign-color-picker" aria-label="'
					+ pickerLabel
					+ '" data-token="'
					+ escapeHtml(name)
					+ '" value="'
					+ escapeHtml(pickerVal)
					+ '">'
					+ '<input type="text" class="nldesign-color-text" aria-label="'
					+ inputLabel
					+ '" data-token="'
					+ escapeHtml(name)
					+ '" value="'
					+ escapeHtml(displayVal)
					+ '">'
					+ '</div>'
			} else {
				inputHtml =
					'<input type="text" class="nldesign-text-input" aria-label="'
					+ inputLabel
					+ '" data-token="'
					+ escapeHtml(name)
					+ '" value="'
					+ escapeHtml(displayVal)
					+ '">'
			}

			return (
				'<div class="nldesign-token-row" data-token-row="'
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
				+ '">↺</button>'
				+ '</div>'
			)
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
						var value = picker.value
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
						if (
							picker !== null
							&& /^#[0-9a-fA-F]{6}$/.test(value) === true
						) {
							picker.value = value
						}
						applyLivePreview(name, value)
						markDirty(name, value, container)
					})
				})

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
						var defaultVal = state.resolved
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
						document.documentElement.style.removeProperty(name)
						tokenEditorState[name].current = defaultVal
						tokenEditorState[name].custom = null
						tokenEditorState[name].isDirty = true
						updateSaveStatus()
					})
				})
		}

		function applyLivePreview(name, value) {
			if (value.trim() === '') {
				document.documentElement.style.removeProperty(name)
			} else {
				document.documentElement.style.setProperty(name, value)
			}
			// Reflect the live token edit into the rich preview.
			updatePreview(tokenSetSelect ? tokenSetSelect.value : '')
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

		function saveOverrides() {
			var overrides = {}
			Object.keys(tokenEditorState).forEach(function (name) {
				var state = tokenEditorState[name]
				var value = state.current.trim()
				if (value !== '' && value !== state.resolved) {
					overrides[name] = value
				}
			})

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
				body: JSON.stringify({ overrides: overrides }),
			})
				.then(function (r) {
					return r.json()
				})
				.then(function (data) {
					if (btn !== null) {
						btn.disabled = false
					}
					if (data.status === 'ok') {
						Object.keys(tokenEditorState).forEach(function (k) {
							tokenEditorState[k].isDirty = false
						})
						updateSaveStatus()
						notify(t('thematiq', 'Token overrides saved.'))
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

		function exportOverrides() {
			var a = document.createElement('a')
			a.href = OC.generateUrl('/apps/thematiq/settings/overrides/export')
			a.download = 'custom-overrides.css'
			document.body.appendChild(a)
			a.click()
			document.body.removeChild(a)
		}

		function importOverrides(file) {
			var formData = new FormData()
			formData.append('file', file)

			fetch(OC.generateUrl('/apps/thematiq/settings/overrides/import'), {
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
								'nldesign',
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

			Promise.all([preview, theming])
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
					var newValues = data.resolved || {}
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
				+ '<p class="settings-hint">'
				+ escapeHtml(
					t(
						'nldesign',
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

					fetch(OC.generateUrl('/apps/thematiq/settings/overrides'), {
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
								OC.generateUrl('/apps/thematiq/settings/overrides'),
								{
									method: 'POST',
									headers: {
										'Content-Type': 'application/json',
										requesttoken: OC.requestToken,
									},
									body: JSON.stringify({ overrides: merged }),
								},
							)
						})
						.then(function (r) {
							return r.json()
						})
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

							// Core theming rides on the same confirm. The sync used to
							// be unreachable from this path (baseline item 3 of
							// MAKEOVER-PLAN.md) and, once reachable, was a second modal;
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
										return plan.mode === 'reset'
											? t(
													'thematiq',
													'Applied. Nextcloud theming reset to its defaults.',
												)
											: t(
													'thematiq',
													'Applied. Nextcloud theming updated.',
												)
									})
									.catch(function (themingError) {
										console.error(
											'Error syncing Nextcloud theming:',
											themingError,
										)
										return t(
											'thematiq',
											'Theme applied, but updating Nextcloud theming failed.',
										)
									})
							}

							return swapped === true
								? t('thematiq', 'Applied.')
								: t('thematiq', 'Token overrides applied.')
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
			trigger.setAttribute('aria-haspopup', 'true')
			trigger.setAttribute('aria-expanded', 'false')
			var triggerLabel = document.createElement('span')
			trigger.appendChild(triggerLabel)

			var panel = document.createElement('div')
			panel.className = 'nldesign-app-dropdown-panel'

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
				var themed = 0
				boxes.forEach(function (b) {
					if (b.checked) {
						themed++
					}
				})
				triggerLabel.textContent = t(
					'nldesign',
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
						opt.hidden =
							q !== ''
							&& opt.getAttribute('data-app-name').indexOf(q) === -1
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

			var disabledApps = []
			listEl
				.querySelectorAll('input[type="checkbox"][data-app-id]')
				.forEach(function (cb) {
					if (cb.checked === false) {
						disabledApps.push(cb.getAttribute('data-app-id'))
					}
				})

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
								'nldesign',
								'App theming saved. Reload an affected app to see changes.',
							)
						}
						notify(
							t(
								'nldesign',
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
						function (entry) {
							return { group: entry.group, tokenSet: entry.tokenSet }
						},
					)
					renderGroupThemingList()
				})
				.catch(function (err) {
					console.error('Error loading group theming:', err)
					listEl.textContent = t(
						'nldesign',
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
				return { group: row.group, tokenSet: row.tokenSet }
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
							function (entry) {
								return {
									group: entry.group,
									tokenSet: entry.tokenSet,
								}
							},
						)
						renderGroupThemingList()
						if (feedback !== null) {
							feedback.textContent = t(
								'nldesign',
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
							'nldesign',
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
							'nldesign',
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
									'nldesign',
									'{pair}: contrast could not be evaluated (non-literal colour).',
								).replace('{pair}', w.pair),
							)
							+ '</li>'
						)
					}
					return (
						'<li>'
						+ escapeHtml(
							t(
								'nldesign',
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
					'nldesign',
					'Not part of the --nldesign-* vocabulary',
				),
				'missing-type': t(
					'nldesign',
					'No $type could be resolved (never guessed)',
				),
				'unsupported-color-space': t('thematiq', 'Unsupported color space'),
				'unsupported-value-shape': t('thematiq', 'Unsupported value shape'),
				'alias-cycle': t('thematiq', 'Alias cycle detected'),
				'alias-target-missing': t('thematiq', 'Alias target does not exist'),
				'alias-depth-exceeded': t(
					'nldesign',
					'Alias chain too deep (more than 10 hops)',
				),
				'duplicate-target': t(
					'nldesign',
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
			}

			return fragment
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
						'nldesign',
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
								'nldesign',
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
									'nldesign',
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
						'nldesign',
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
					'nldesign',
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
					badge.textContent = t('thematiq', 'WCAG AA OK')
				}
				row.appendChild(badge)

				var downloadBtn = document.createElement('button')
				downloadBtn.type = 'button'
				downloadBtn.className = 'nldesign-btn nldesign-btn--small'
				downloadBtn.textContent = t('thematiq', 'Download')
				downloadBtn.addEventListener('click', function () {
					window.location = OC.generateUrl(
						'/apps/thematiq/settings/tokensets/custom/'
							+ encodeURIComponent(set.id)
							+ '/export',
					)
				})
				row.appendChild(downloadBtn)

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
					'nldesign',
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
									notify(
										t('thematiq', 'Custom token set deleted.'),
									)
								})
							} else {
								notify(
									t(
										'nldesign',
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

		// Initialise the custom token set panel on page load.
		initCustomTokenSets()

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
					'nldesign',
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
										'nldesign',
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
					window.location = OC.generateUrl(
						'/apps/thematiq/settings/audit/export',
					)
				})
			}
		}

		function renderAuditMessage(tableBody, message) {
			tableBody.innerHTML = ''
			var row = document.createElement('tr')
			var cell = document.createElement('td')
			cell.colSpan = 4
			cell.className = 'settings-hint'
			cell.textContent = message
			row.appendChild(cell)
			tableBody.appendChild(row)
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

				var tsCell = document.createElement('td')
				tsCell.textContent = entry.ts || ''
				row.appendChild(tsCell)

				var actorCell = document.createElement('td')
				actorCell.textContent = entry.actor || ''
				row.appendChild(actorCell)

				var actionCell = document.createElement('td')
				actionCell.textContent = entry.action || ''
				row.appendChild(actionCell)

				var detailsCell = document.createElement('td')
				detailsCell.textContent = formatAuditDetails(entry)
				row.appendChild(detailsCell)

				tableBody.appendChild(row)
			})
		}

		function formatAuditValue(value) {
			if (value === null || value === undefined) {
				return ''
			}
			if (typeof value === 'object') {
				try {
					return JSON.stringify(value)
				} catch (e) {
					return String(value)
				}
			}
			return String(value)
		}

		function formatAuditDetails(entry) {
			var parts = []
			if (entry.old !== undefined && entry.old !== null) {
				parts.push(
					t('thematiq', 'from {value}').replace(
						'{value}',
						formatAuditValue(entry.old),
					),
				)
			}
			if (entry.new !== undefined && entry.new !== null) {
				parts.push(
					t('thematiq', 'to {value}').replace(
						'{value}',
						formatAuditValue(entry.new),
					),
				)
			}
			return parts.join(' ')
		}

		initAuditLog()

		/* ==========================================================================
		 * CONFIGURATION BUNDLE — complete-config OTAP promotion download/upload
		 * (config-portability spec). Distinct from the token-editor overrides
		 * download/upload above: this bundle covers the token set, both toggles,
		 * per-app exclusions, overrides CSS, custom token sets, the email footer,
		 * and the upstream-freshness toggle in one JSON file.
		 * ========================================================================== */

		function downloadConfigBundle() {
			window.location = OC.generateUrl('/apps/thematiq/settings/config/export')
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
								'nldesign',
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
								'nldesign',
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
								'nldesign',
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
								'nldesign',
								'A different mail template class is already configured ({class}); nldesign will not overwrite it.',
								{ class: data.class },
							)
						}
						notify(
							t(
								'nldesign',
								'A different mail template class is already configured.',
							),
						)
						return
					}

					if (data && data.error === 'invalid_footer') {
						notify(
							t(
								'nldesign',
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
					'nldesign',
					'Token set {name} has upstream update {version} — review & apply',
					{ name: name, version: version },
				)
			}
			return t(
				'nldesign',
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
										'nldesign',
										'Upstream token update checks enabled.',
									)
								: t(
										'nldesign',
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
