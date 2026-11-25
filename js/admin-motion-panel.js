/**
 * Motion tokens (the TqBeweging board), the panel of the Theming page at #motion.
 * The token editor of the overview is lent to the panel while it is open and shows only
 * its three motion rows (Animation quick, Animation slow, Animation easing) and the switch
 * that unlocks Nextcloud's base tokens, so the rows save exactly as on the overview. The
 * preview plays a dialog and a notification with the values in the rows.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqMotionPanel() {
	/** The motion tokens the panel shows. */
	var TOKENS = ['--animation-quick', '--animation-slow', '--nldesign-animation-easing']
	var lent = null

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
	 * A duration in milliseconds from a number and its unit, or null when it is not one.
	 *
	 * @param {string} value The number as typed.
	 * @param {string} unit `ms` or `s`.
	 * @return {number|null} The milliseconds.
	 */
	function toMs(value, unit) {
		var number = parseFloat(value)
		if (isNaN(number) === true || number < 0) {
			return null
		}
		return unit === 's' ? number * 1000 : number
	}

	/**
	 * The easing a keyword or four curve points describe, or null when the curve is not valid
	 * (both x values must lie between 0 and 1, as the server checks).
	 *
	 * @param {string} keyword The chosen keyword, or `custom`.
	 * @param {Array<string>} points x1, y1, x2, y2 as typed.
	 * @return {string|null} The CSS easing.
	 */
	function toEasing(keyword, points) {
		if (keyword !== 'custom') {
			return keyword || null
		}
		var numbers = (points || []).map(parseFloat)
		if (numbers.length !== 4 || numbers.some(isNaN) === true) {
			return null
		}
		if (numbers[0] < 0 || numbers[0] > 1 || numbers[2] < 0 || numbers[2] > 1) {
			return null
		}
		return 'cubic-bezier(' + numbers.join(', ') + ')'
	}

	/**
	 * The line under the preview: the duration and the easing it plays with.
	 *
	 * @param {number|null} ms The duration.
	 * @param {string|null} easing The easing.
	 * @param {boolean} reduced Whether the system asks for reduced motion.
	 * @return {string} The line.
	 */
	function readout(ms, easing, reduced) {
		if (ms === null || easing === null) {
			return tr('The values in the rows are not a valid motion yet.')
		}
		return tr('Duration {ms} ms, easing {easing}. Replays the dialog and the notification.', {
			ms: reduced === true ? 0 : Math.round(ms),
			easing: easing,
		})
	}

	/**
	 * The values in the editor's motion rows.
	 *
	 * @param {ParentNode} root Where the rows are.
	 * @return {{slow: number|null, easing: string|null}} The slow duration and the easing.
	 */
	function current(root) {
		var number = root.querySelector('.nldesign-duration-number[data-token="--animation-slow"]')
		var unit = root.querySelector('.nldesign-duration-unit[data-token="--animation-slow"]')
		var select = root.querySelector('.nldesign-easing-select[data-token="--nldesign-animation-easing"]')
		var points = Array.prototype.map.call(
			root.querySelectorAll('.nldesign-easing-point[data-token="--nldesign-animation-easing"]'),
			function (input) {
				return input.value
			},
		)
		return {
			slow: number !== null ? toMs(number.value, unit !== null ? unit.value : 'ms') : null,
			easing: select !== null ? toEasing(select.value, points) : null,
		}
	}

	/**
	 * Whether the system asks for reduced motion.
	 *
	 * @return {boolean} The answer.
	 */
	function reducedMotion() {
		return typeof window.matchMedia === 'function'
			&& window.matchMedia('(prefers-reduced-motion: reduce)').matches === true
	}

	/**
	 * Write the readout and the base token notice.
	 *
	 * @param {HTMLElement} section The panel.
	 * @return {void}
	 */
	function refresh(section) {
		var values = current(section)
		var line = section.querySelector('.thematiq-motion-panel__readout')
		if (line !== null) {
			line.textContent = readout(values.slow, values.easing, reducedMotion())
		}
		var unlock = document.getElementById('nldesign-base-unlock')
		var notice = section.querySelector('.thematiq-motion-panel__notice')
		if (notice !== null) {
			notice.textContent = unlock !== null && unlock.checked === true
				? tr('These are Nextcloud base tokens. They are open because "Also edit Nextcloud\'s base tokens" is on; a change affects every page.')
				: tr('These are Nextcloud base tokens. Tick "Also edit Nextcloud\'s base tokens" to change them; a change affects every page.')
		}
	}

	/**
	 * Play the dialog and the notification with the values in the rows. Under reduced motion
	 * nothing moves.
	 *
	 * @param {HTMLElement} section The panel.
	 * @return {void}
	 */
	function play(section) {
		refresh(section)
		var values = current(section)
		if (values.slow === null || values.easing === null || reducedMotion() === true) {
			return
		}
		section.querySelectorAll('.thematiq-motion-panel__sample').forEach(function (sample) {
			if (typeof sample.animate === 'function') {
				sample.animate(
					[{ opacity: 0, transform: 'translateY(16px)' }, { opacity: 1, transform: 'none' }],
					{ duration: values.slow, easing: values.easing },
				)
			}
		})
	}

	/**
	 * Lend the token editor to the panel, leaving a marker where it stood, on the editor tab
	 * that holds the motion rows.
	 *
	 * @param {HTMLElement} section The panel.
	 * @return {void}
	 */
	function lend(section) {
		var slot = section.querySelector('.thematiq-motion-panel__editor')
		var editor = document.getElementById('nldesign-token-editor')
		var tab = document.querySelector('.nldesign-pg-selector .nldesign-tab-btn[data-tab="content"]')
		if (tab !== null && tab.classList.contains('active') === false) {
			tab.click()
		}
		if (editor === null || slot === null || slot.contains(editor)) {
			return
		}
		var marker = document.createComment('thematiq: motion')
		editor.parentNode.insertBefore(marker, editor)
		lent = { part: editor, marker: marker }
		slot.appendChild(editor)
	}

	/**
	 * Give the token editor back to the overview.
	 *
	 * @return {void}
	 */
	function giveBack() {
		if (lent === null) {
			return
		}
		lent.marker.parentNode.insertBefore(lent.part, lent.marker)
		lent.marker.parentNode.removeChild(lent.marker)
		lent = null
	}

	/**
	 * A header button that presses one of the editor's own controls.
	 *
	 * @param {string} label The text.
	 * @param {string} id The control's id.
	 * @param {boolean} primary Whether it is the main action.
	 * @return {HTMLElement} The button.
	 */
	function headerButton(label, id, primary) {
		var button = document.createElement('button')
		button.type = 'button'
		button.className = 'thematiq-page-header__action' + (primary === true ? ' thematiq-page-header__action--primary' : '')
		button.textContent = label
		button.addEventListener('click', function () {
			var control = document.getElementById(id)
			if (control !== null) {
				control.click()
			}
		})
		return button
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { TOKENS: TOKENS, toMs: toMs, toEasing: toEasing, readout: readout, current: current }
	}

	var section = document.querySelector('section[data-thematiq-panel="motion"]')
	if (section !== null) {
		section.addEventListener('input', function () {
			refresh(section)
		})
		section.addEventListener('change', function () {
			refresh(section)
		})
		var button = section.querySelector('.thematiq-motion-panel__play')
		if (button !== null) {
			button.addEventListener('click', function () {
				play(section)
			})
		}
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('motion', {
			title: tr('Motion tokens'),
			crumb: tr('Motion'),
			crumbs: function () {
				var select = document.getElementById('nldesign-token-set-select')
				var id = select !== null ? select.value : ''
				return [
					{ text: tr('Token sets'), href: '#' },
					{ text: id, href: '#set/' + encodeURIComponent(id) },
					{ text: tr('Components'), href: '#components' },
				]
			},
			intro: function () {
				return tr('Speed and curve of every transition')
			},
			actions: function () {
				return [
					headerButton(tr('Download'), 'nldesign-export-btn', false),
					headerButton(tr('Save'), 'nldesign-save-btn', true),
				]
			},
			onOpen: function (panel) {
				lend(panel)
				refresh(panel)
			},
			onClose: giveBack,
		})
	}
})()
