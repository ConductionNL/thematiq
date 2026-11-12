/**
 * @vitest-environment jsdom
 *
 * The inert markup renderer admin.js and the playground render through: it keeps
 * the structure and attributes the panels rely on and drops whatever can run code.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec exclude unit test of the shared markup renderer
 */

import { describe, expect, it } from 'vitest'
import markupRender from '../../js/lib/markupRender.js'

describe('markupRender', () => {
	it('replaces the children and keeps data, style, aria and form attributes', () => {
		const target = document.createElement('div')
		target.appendChild(document.createElement('span'))

		markupRender.render(
			target,
			'<div class="row" data-token="--x" style="color:red" aria-label="Row">'
				+ '<input type="checkbox" id="c" checked><label for="c">Label</label></div>'
				+ '<svg viewBox="0 0 1 1"><path d="M0 0"></path></svg>',
		)

		expect(target.children).toHaveLength(2)
		const row = target.querySelector('.row')
		expect(row.dataset.token).toBe('--x')
		expect(row.getAttribute('style')).toBe('color:red')
		expect(row.getAttribute('aria-label')).toBe('Row')
		expect(target.querySelector('#c').checked).toBe(true)
		expect(target.querySelector('svg path').namespaceURI).toBe('http://www.w3.org/2000/svg')
	})

	it('drops scripts, frames, handlers and javascript: URLs', () => {
		const target = document.createElement('div')

		markupRender.render(
			target,
			'<p onclick="x()">t<script>window.ran = true</script></p>'
				+ '<iframe src="https://example.org"></iframe>'
				+ '<a href=" javascript:alert(1)">a</a><a href="#ok">b</a>'
				+ '<img src="data:image/png;base64,AA" onerror="x()">',
		)

		expect(target.querySelector('script')).toBe(null)
		expect(target.querySelector('iframe')).toBe(null)
		expect(target.querySelector('p').hasAttribute('onclick')).toBe(false)
		expect(target.querySelector('img').hasAttribute('onerror')).toBe(false)
		expect(target.querySelector('img').getAttribute('src')).toBe('data:image/png;base64,AA')
		const links = target.querySelectorAll('a')
		expect(links[0].hasAttribute('href')).toBe(false)
		expect(links[1].getAttribute('href')).toBe('#ok')
		expect(window.ran).toBeUndefined()
	})

	it('empties the element for empty markup', () => {
		const target = document.createElement('div')
		target.textContent = 'old'

		markupRender.render(target, '')

		expect(target.childNodes).toHaveLength(0)
	})
})
