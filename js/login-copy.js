/**
 * The login page of the light workplace, as the NcLogin boards draw it.
 *
 * Loaded only on the login page while the workplace layout resolves to
 * `light` (LoginCopyService), with the copy of the page's token set as
 * initial state (`thematiq/login-copy`): title, subtitle, ssoLabel,
 * usernameLabel, divider, footer, footerHint, footerLinkLabel, footerLinkUrl.
 * Every key is optional.
 *
 * - An intro block (title and subtitle) becomes the first child of the
 *   login card. Nextcloud's own headline is hidden by css/workplace-layout.css
 *   while the intro is there; without a configured title the intro repeats
 *   Nextcloud's headline, so the words never disappear.
 * - The alternative logins (the SSO button an app such as user_oidc registers
 *   through IAlternativeLogin) move above the form, in the DOM and not only
 *   on screen, so the focus order matches what is drawn. A divider ("of")
 *   follows them. With exactly one alternative login the SSO label replaces
 *   its name. The card is marked `data-thematiq-sso`, and the stylesheet then
 *   draws the password button as the secondary action.
 * - The username label, the footer line and an optional footer hint with a
 *   link are written as text. Nextcloud's legal links in the footer stay.
 *
 * Text only, never markup. Nextcloud re-renders parts of the card (the reset
 * password form and back); every step is idempotent and runs again then.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
 */
;(function thematiqLoginCopyInit() {
	var INTRO = 'thematiq-login-intro'
	var DIVIDER = 'thematiq-login-divider'
	var HINT = 'thematiq-login-footer-hint'

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqLoginCopyMain)
	} else {
		thematiqLoginCopyMain()
	}

	/**
	 * Read the copy from the initial state.
	 *
	 * @return {object|null} The copy, or null when there is none to read.
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
	 */
	function readCopy() {
		if (
			typeof OCP === 'undefined'
			|| !OCP.InitialState
			|| typeof OCP.InitialState.loadState !== 'function'
		) {
			return null
		}
		try {
			var copy = OCP.InitialState.loadState('thematiq', 'login-copy', null)
			return copy && typeof copy === 'object' ? copy : null
		} catch (e) {
			return null
		}
	}

	/**
	 * A non-empty string from the copy, or the empty string.
	 *
	 * @param {object} copy The copy.
	 * @param {string} key The key.
	 * @return {string} The text.
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
	 */
	function text(copy, key) {
		return typeof copy[key] === 'string' ? copy[key].trim() : ''
	}

	/**
	 * Write text into an element only when it differs, so the observer that
	 * calls this does not wake itself.
	 *
	 * @param {Element} el The element.
	 * @param {string} value The text.
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
	 */
	function setText(el, value) {
		if (el.textContent !== value) {
			el.textContent = value
		}
	}

	/**
	 * The intro: title and subtitle as the first child of the card.
	 *
	 * @param {Element} box The login card.
	 * @param {object} copy The copy.
	 * @return {Element|null} The intro, or null while the card shows no login form.
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
	 */
	function placeIntro(box, copy) {
		var intro = box.querySelector(':scope > .' + INTRO)
		var headline = box.querySelector('.login-form__headline')
		var title = text(copy, 'title') || (headline ? headline.textContent.trim() : '')
		if (title === '') {
			return intro
		}
		if (intro === null) {
			intro = document.createElement('div')
			intro.className = INTRO
			var heading = document.createElement('h2')
			heading.className = INTRO + '__title'
			intro.appendChild(heading)
			box.insertBefore(intro, box.firstChild)
		} else if (box.firstElementChild !== intro) {
			box.insertBefore(intro, box.firstChild)
		}
		setText(intro.querySelector('.' + INTRO + '__title'), title)

		var subtitle = text(copy, 'subtitle')
		var sub = intro.querySelector('.' + INTRO + '__subtitle')
		if (subtitle !== '' && sub === null) {
			sub = document.createElement('p')
			sub.className = INTRO + '__subtitle'
			intro.appendChild(sub)
		}
		if (sub !== null) {
			setText(sub, subtitle)
		}
		box.setAttribute('data-thematiq-login-intro', '')
		return intro
	}

	/**
	 * The alternative logins above the form, then the divider.
	 *
	 * @param {Element} box The login card.
	 * @param {Element|null} intro The intro, after which they go.
	 * @param {object} copy The copy.
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
	 */
	function placeAlternatives(box, intro, copy) {
		var alt = box.querySelector(':scope > #alternative-logins')
		var buttons = alt ? alt.querySelectorAll('a, button') : []
		if (alt === null || buttons.length === 0) {
			box.removeAttribute('data-thematiq-sso')
			return
		}

		var anchor = intro ? intro.nextSibling : box.firstChild
		if (anchor !== alt) {
			box.insertBefore(alt, anchor)
		}

		var divider = box.querySelector(':scope > .' + DIVIDER)
		if (divider === null) {
			divider = document.createElement('div')
			divider.className = DIVIDER
			divider.appendChild(document.createElement('span'))
		}
		if (alt.nextSibling !== divider) {
			box.insertBefore(divider, alt.nextSibling)
		}
		setText(divider.firstChild, text(copy, 'divider'))

		var label = text(copy, 'ssoLabel')
		if (label !== '' && buttons.length === 1) {
			var target = buttons[0].querySelector('.button-vue__text') || buttons[0]
			setText(target, label)
		}
		box.setAttribute('data-thematiq-sso', '')
	}

	/**
	 * The label of the username field.
	 *
	 * @param {Element} box The login card.
	 * @param {object} copy The copy.
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
	 */
	function labelUsername(box, copy) {
		var label = text(copy, 'usernameLabel')
		var el = label ? box.querySelector('label[for="user"]') : null
		if (el !== null) {
			setText(el, label)
		}
	}

	/**
	 * The footer line, keeping Nextcloud's legal links, and the hint.
	 *
	 * @param {object} copy The copy.
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
	 */
	function writeFooter(copy) {
		var footer = document.querySelector('body > footer.guest-box')
		var info = footer ? footer.querySelector('p.info') : null
		if (info === null) {
			return
		}
		var line = text(copy, 'footer')
		if (line !== '') {
			// Everything before the legal links is Nextcloud's name and slogan.
			var legal = info.querySelector('.footer__legal-links')
			var stop = legal ? legal.previousSibling : null
			while (info.firstChild && info.firstChild !== stop && info.firstChild !== legal) {
				info.removeChild(info.firstChild)
			}
			if (stop && stop.nodeName === 'BR') {
				info.insertBefore(document.createTextNode(line), stop)
			} else {
				info.insertBefore(document.createTextNode(line), info.firstChild)
			}
			footer.classList.remove('hidden')
		}

		var hint = text(copy, 'footerHint')
		var linkLabel = text(copy, 'footerLinkLabel')
		var linkUrl = text(copy, 'footerLinkUrl')
		var hasLink = linkLabel !== '' && /^(https?:\/\/|\/(?!\/))/i.test(linkUrl)
		if ((hint === '' && !hasLink) || footer.querySelector('.' + HINT) !== null) {
			return
		}
		var p = document.createElement('p')
		p.className = 'info ' + HINT
		if (hint !== '') {
			p.appendChild(document.createTextNode(hint + (hasLink ? ' ' : '')))
		}
		if (hasLink) {
			var a = document.createElement('a')
			a.href = linkUrl
			a.textContent = linkLabel
			p.appendChild(a)
		}
		footer.appendChild(p)
		footer.classList.remove('hidden')
	}

	/**
	 * Apply the copy now and whenever the card re-renders.
	 *
	 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
	 */
	function thematiqLoginCopyMain() {
		var copy = readCopy()
		if (copy === null) {
			return
		}
		writeFooter(copy)

		var apply = function () {
			var box = document.querySelector('.guest-content .guest-box.login-box')
			if (box === null) {
				return
			}
			var intro = placeIntro(box, copy)
			placeAlternatives(box, intro, copy)
			labelUsername(box, copy)
		}

		apply()
		var root = document.querySelector('.guest-content') || document.body
		if (typeof MutationObserver === 'function') {
			new MutationObserver(apply).observe(root, {
				childList: true,
				subtree: true,
			})
		}
	}
})()
