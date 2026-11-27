/**
 * @vitest-environment jsdom
 *
 * js/lib/buttonIcons.js: every button that names a board icon gets one icon
 * element, also when a panel adds the button later or sets its text again.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-each-tool-is-the-page-or-panel-its-board-draws-req-aset-103
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest'

const tick = () => new Promise((resolve) => setTimeout(resolve, 0))

function icons(el) {
	return el.querySelectorAll(':scope > .thematiq-button-icon')
}

describe('button icons as elements', () => {
	beforeEach(async () => {
		document.body.innerHTML =
			'<button id="a" data-thematiq-icon="download">Download</button><button id="b">Plain</button>'
		await import('../../js/lib/buttonIcons.js?t=' + Math.random())
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete window.ThematiqButtonIcons
	})

	it('puts one hidden icon before the label of a button that names one', () => {
		const button = document.getElementById('a')
		expect(icons(button)).toHaveLength(1)
		expect(button.firstChild.getAttribute('aria-hidden')).toBe('true')
		expect(button.textContent).toBe('Download')
		expect(icons(document.getElementById('b'))).toHaveLength(0)
	})

	it('decorates a button a panel adds later', async () => {
		const added = document.createElement('div')
		added.innerHTML = '<button data-thematiq-icon="plus">Add token</button>'
		document.body.appendChild(added)
		await tick()
		expect(icons(added.firstChild)).toHaveLength(1)
	})

	it('puts the icon back when a script sets the text again, once', async () => {
		const button = document.getElementById('a')
		button.textContent = 'Download again'
		await tick()
		await tick()
		expect(icons(button)).toHaveLength(1)
		expect(button.textContent).toBe('Download again')
	})

	it('does not add a second icon on a second pass', () => {
		window.ThematiqButtonIcons.decorate(document.body)
		expect(icons(document.getElementById('a'))).toHaveLength(1)
	})
})
