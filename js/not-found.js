/**
 * Nextcloud's "page not found" page in the light workplace, as the
 * NietGevonden board draws it.
 *
 * Loaded only on that page while the workplace layout resolves to `light`
 * (NotFoundCopyService), with the copy of the page's token set as initial
 * state (`thematiq/not-found-copy`): eyebrow, title, text, linksHeading,
 * links ([{label, url}]) and hint. Every key is optional.
 *
 * It marks the card `data-thematiq-not-found` (css/workplace-layout.css then
 * lays it out as the board), puts the eyebrow above the title, writes the
 * title and the text, and adds the heading with the links and the closing
 * line. With links, Nextcloud's own "Back to …" button gives way to them.
 * Text only, never markup.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
 */
;(function thematiqNotFoundInit() {
	var BLOCK = 'thematiq-not-found'
	var SVG_NS = 'http://www.w3.org/2000/svg'

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqNotFoundMain)
	} else {
		thematiqNotFoundMain()
	}

	/**
	 * A non-empty string from the copy, or the empty string.
	 *
	 * @param {object} copy The copy.
	 * @param {string} key The key.
	 * @return {string} The text.
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
	 */
	function text(copy, key) {
		return typeof copy[key] === 'string' ? copy[key].trim() : ''
	}

	/**
	 * The links that may be shown: a label and an http(s) address or a path
	 * on this server.
	 *
	 * @param {object} copy The copy.
	 * @return {Array<{label: string, url: string}>} The links.
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
	 */
	function links(copy) {
		if (!Array.isArray(copy.links)) {
			return []
		}
		return copy.links.filter(function (link) {
			return (
				link
				&& typeof link.label === 'string'
				&& link.label.trim() !== ''
				&& typeof link.url === 'string'
				&& /^(https?:\/\/|\/(?!\/))/i.test(link.url)
			)
		})
	}

	/**
	 * A chevron, hidden from assistive technology.
	 *
	 * @return {SVGElement} The icon.
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
	 */
	function chevron() {
		var svg = document.createElementNS(SVG_NS, 'svg')
		svg.setAttribute('class', BLOCK + '__chevron')
		svg.setAttribute('viewBox', '0 0 24 24')
		svg.setAttribute('width', '20')
		svg.setAttribute('height', '20')
		svg.setAttribute('fill', 'currentColor')
		svg.setAttribute('aria-hidden', 'true')
		var path = document.createElementNS(SVG_NS, 'path')
		path.setAttribute(
			'd',
			'M8.59,16.58L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.58Z',
		)
		svg.appendChild(path)
		return svg
	}

	/**
	 * An element with a class and a text.
	 *
	 * @param {string} tag The tag.
	 * @param {string} part The BEM part after the block.
	 * @param {string} value The text.
	 * @return {HTMLElement} The element.
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
	 */
	function el(tag, part, value) {
		var node = document.createElement(tag)
		node.className = BLOCK + '__' + part
		node.textContent = value
		return node
	}

	/**
	 * Write the copy into the card.
	 *
	 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
	 */
	function thematiqNotFoundMain() {
		if (
			typeof OCP === 'undefined'
			|| !OCP.InitialState
			|| typeof OCP.InitialState.loadState !== 'function'
		) {
			return
		}
		var copy = null
		try {
			copy = OCP.InitialState.loadState('thematiq', 'not-found-copy', null)
		} catch (e) {
			return
		}
		if (!copy || typeof copy !== 'object') {
			return
		}

		var card = document.querySelector('.guest-content .body-login-container')
		if (card === null || card.hasAttribute('data-' + BLOCK)) {
			return
		}
		card.setAttribute('data-' + BLOCK, '')

		var title = card.querySelector('h2')
		var eyebrow = text(copy, 'eyebrow')
		if (eyebrow !== '' && title !== null) {
			card.insertBefore(el('p', 'eyebrow', eyebrow), title)
		}
		if (text(copy, 'title') !== '' && title !== null) {
			title.textContent = text(copy, 'title')
		}
		var info = card.querySelector('p.infogroup')
		if (text(copy, 'text') !== '' && info !== null) {
			info.textContent = text(copy, 'text')
		}

		var list = links(copy)
		if (list.length > 0) {
			var back = card.querySelector('a.button.primary')
			if (back !== null && back.parentElement !== card) {
				back.parentElement.classList.add(BLOCK + '__replaced')
			}
			var heading = text(copy, 'linksHeading')
			if (heading !== '') {
				card.appendChild(el('h3', 'heading', heading))
			}
			var ul = document.createElement('ul')
			ul.className = BLOCK + '__links'
			list.forEach(function (link) {
				var li = document.createElement('li')
				var a = document.createElement('a')
				a.className = BLOCK + '__link'
				a.href = link.url
				a.appendChild(chevron())
				a.appendChild(document.createTextNode(link.label.trim()))
				li.appendChild(a)
				ul.appendChild(li)
			})
			card.appendChild(ul)
		}

		var hint = text(copy, 'hint')
		if (hint !== '') {
			card.appendChild(el('p', 'hint', hint))
		}
	}
})()
